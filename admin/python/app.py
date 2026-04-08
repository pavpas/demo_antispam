"""AntiSpam Shield — Python (Flask) Admin Panel"""

import sqlite3
import uuid
import json
import os
from datetime import datetime, timedelta
from functools import wraps

import bcrypt
from flask import (
    Flask, render_template, request, session, redirect, url_for, g
)

app = Flask(__name__)
app.secret_key = 'antispam-flask-session-key-change-in-production'

DB_PROVIDER = os.environ.get('DB_PROVIDER', 'sqlite')
DB_PATH = os.environ.get('ANTISPAM_DB_PATH',
    os.path.join(os.path.dirname(__file__), '..', '..', 'data', 'antispam.db'))


# ── Reverse proxy prefix support ─────────────────────────────

class PrefixMiddleware:
    """WSGI middleware that sets SCRIPT_NAME for reverse proxy prefix."""
    def __init__(self, wsgi_app, prefix=''):
        self.app = wsgi_app
        self.prefix = prefix

    def __call__(self, environ, start_response):
        if self.prefix:
            environ['SCRIPT_NAME'] = self.prefix
            path = environ.get('PATH_INFO', '')
            if path.startswith(self.prefix):
                environ['PATH_INFO'] = path[len(self.prefix):]
        return self.app(environ, start_response)


prefix = os.environ.get('APP_BASE_PATH', '')
if prefix:
    app.wsgi_app = PrefixMiddleware(app.wsgi_app, prefix=prefix)


# ── Translations ──────────────────────────────────────────────

TRANSLATIONS = {
    'ru': {
        'app_title': 'AntiSpam Shield',
        'app_subtitle': 'Панель управления',
        'nav_projects': 'Проекты',
        'nav_logout': 'Выйти',
        'login_title': 'Вход в систему',
        'login_username': 'Логин',
        'login_password': 'Пароль',
        'login_submit': 'Войти',
        'login_error': 'Неверный логин или пароль',
        'projects_title': 'Проекты',
        'projects_add': 'Добавить проект',
        'projects_name': 'Название',
        'projects_domain': 'Домен',
        'projects_stats': 'Статистика',
        'projects_details': 'Подробно',
        'projects_delete': 'Удалить',
        'projects_delete_confirm': 'Вы уверены, что хотите удалить проект',
        'projects_empty': 'Нет проектов. Создайте первый!',
        'new_project_title': 'Новый проект',
        'new_project_name': 'Мой сайт',
        'new_project_domain': 'example.com',
        'new_project_create': 'Создать',
        'new_project_cancel': 'Отмена',
        'tab_keys': 'Ключи',
        'tab_frontend': 'Подключение на фронтенде',
        'tab_backend': 'Подключение на сервере',
        'tab_testing': 'Тестирование',
        'tab_rules': 'Правила фильтрации',
        'tab_logs': 'Логи',
        'rules_title': 'Правила фильтрации',
        'rules_add': 'Добавить правило',
        'rules_edit': 'Редактировать правило',
        'rules_name': 'Название правила',
        'rules_type': 'Тип',
        'rules_value': 'Значение',
        'rules_action': 'Действие',
        'rules_block': 'Блокировать',
        'rules_allow': 'Разрешить',
        'rules_priority': 'Приоритет',
        'rules_active': 'Активно',
        'rules_inactive': 'Неактивно',
        'rules_save': 'Сохранить',
        'rules_cancel': 'Отмена',
        'rules_delete': 'Удалить',
        'rules_delete_confirm': 'Удалить правило',
        'rules_empty': 'Нет правил фильтрации.',
        'rules_type_ip': 'IP адрес',
        'rules_type_ip_range': 'Диапазон IP (CIDR)',
        'rules_type_user_agent': 'User-Agent',
        'rules_type_header': 'HTTP заголовок',
        'rules_type_score': 'Порог скора',
        'logs_title': 'Логи верификации',
        'logs_all': 'Все',
        'logs_successful': 'Успешные',
        'logs_blocked': 'Заблокированные',
        'logs_filtered': 'Отфильтрованные',
        'logs_date': 'Дата/Время',
        'logs_ip': 'IP адрес',
        'logs_ua': 'User-Agent',
        'logs_score': 'Скор',
        'logs_status': 'Статус',
        'logs_rule': 'Совпавшее правило',
        'logs_no_data': 'Нет логов для выбранного фильтра',
        'logs_human': 'Человек',
        'logs_bot': 'Бот',
        'logs_filter_blocked': 'Отфильтровано',
        'logs_prev': 'Назад',
        'logs_next': 'Вперёд',
        'logs_page': 'Страница',
        'logs_filter_ip': 'Фильтр по IP...',
        'key_public': 'Публичный ключ',
        'key_private': 'Приватный ключ',
        'key_warning': 'Никогда не публикуйте приватный ключ! Используйте его только на стороне сервера.',
        'copy': 'Копировать',
        'copied': 'Скопировано!',
        'frontend_title': 'Подключение JavaScript SDK',
        'frontend_auto_title': 'Вариант 1: Автоматический режим (auto=1, по умолчанию)',
        'frontend_auto_desc': 'Скрипт автоматически найдёт все формы на странице и защитит их.',
        'frontend_auto_step': 'Добавьте в <head> или перед </body> вашего сайта:',
        'frontend_manual_title': 'Вариант 2: Ручной режим (auto=0)',
        'frontend_manual_desc': 'Скрипт только подключается и собирает данные. Вы сами указываете какие формы защищать.',
        'frontend_manual_step': 'Подключите скрипт с параметром auto=0:',
        'frontend_manual_examples': 'Затем защитите нужные формы по селектору:',
        'frontend_by_id': 'По ID элемента:',
        'frontend_by_class': 'По CSS-классу:',
        'frontend_by_selector': 'По произвольному CSS-селектору:',
        'frontend_note': 'SDK автоматически добавит скрытое поле _antispam_token в каждую защищённую форму перед отправкой.',
        'backend_title': 'Верификация на сервере',
        'backend_desc': 'После отправки формы, ваш сервер получит поле _antispam_token. Отправьте его на наш API для проверки:',
        'backend_endpoint': 'Endpoint верификации',
        'backend_request': 'Запрос',
        'backend_response': 'Ответ',
        'backend_examples': 'Примеры интеграции',
        'backend_score_note': 'score — оценка от 0.0 (бот) до 1.0 (человек). Рекомендуемый порог: 0.5',
        'testing_title': 'Тестовая форма',
        'testing_desc': 'Проверьте работу антиспама для этого проекта. Заполните форму и отправьте — SDK соберёт поведенческие сигналы и сгенерирует токен, затем сервер его проверит.',
        'testing_name': 'Имя',
        'testing_email': 'Email',
        'testing_message': 'Сообщение',
        'testing_submit': 'Отправить форму',
        'testing_verifying': 'Проверка...',
        'testing_result_human': 'Человек обнаружен!',
        'testing_result_bot': 'Обнаружен бот или верификация не пройдена.',
        'testing_result_error': 'Ошибка верификации',
        'testing_no_token': 'Токен _antispam_token не найден. SDK мог не загрузиться.',
        'stats_title': 'Статистика',
        'stats_total': 'Всего обращений',
        'stats_success': 'Успешных (человек)',
        'stats_rejected': 'Отклонено (бот)',
        'stats_chart': 'График обращений по дням',
        'stats_period': 'Период',
        'stats_7d': '7 дней',
        'stats_30d': '30 дней',
        'stats_90d': '90 дней',
        'stats_no_data': 'Нет данных за выбранный период',
        'tab_fields': 'Поля',
        'fields_title': 'Пользовательские поля',
        'fields_add': 'Добавить поле',
        'fields_name': 'Название поля',
        'fields_empty': 'Нет пользовательских полей. Добавьте поля для фильтрации логов по параметрам.',
        'fields_delete': 'Удалить',
        'fields_delete_confirm': 'Удалить поле',
        'fields_placeholder': 'например: form, method, source',
        'logs_data': 'Данные',
        'stats_from': 'С',
        'stats_to': 'По',
        'stats_show': 'Показать',
        'stats_custom_range': 'Свой период',
        'logs_all_fields': 'Все',
        'back': 'Назад',
        'rules_conditions': 'Условия',
        'rules_add_condition': 'Добавить условие',
        'rules_conditions_hint': 'Все условия должны совпасть (логика И)',
        'rules_condition': 'Условие',
    },
    'en': {
        'app_title': 'AntiSpam Shield',
        'app_subtitle': 'Control Panel',
        'nav_projects': 'Projects',
        'nav_logout': 'Logout',
        'login_title': 'Sign In',
        'login_username': 'Username',
        'login_password': 'Password',
        'login_submit': 'Sign In',
        'login_error': 'Invalid username or password',
        'projects_title': 'Projects',
        'projects_add': 'Add Project',
        'projects_name': 'Name',
        'projects_domain': 'Domain',
        'projects_stats': 'Statistics',
        'projects_details': 'Details',
        'projects_delete': 'Delete',
        'projects_delete_confirm': 'Are you sure you want to delete project',
        'projects_empty': 'No projects yet. Create your first one!',
        'new_project_title': 'New Project',
        'new_project_name': 'My Website',
        'new_project_domain': 'example.com',
        'new_project_create': 'Create',
        'new_project_cancel': 'Cancel',
        'tab_keys': 'Keys',
        'tab_frontend': 'Frontend Integration',
        'tab_backend': 'Server Integration',
        'tab_testing': 'Testing',
        'tab_rules': 'Filter Rules',
        'tab_logs': 'Logs',
        'rules_title': 'Filter Rules',
        'rules_add': 'Add Rule',
        'rules_edit': 'Edit Rule',
        'rules_name': 'Rule Name',
        'rules_type': 'Type',
        'rules_value': 'Value',
        'rules_action': 'Action',
        'rules_block': 'Block',
        'rules_allow': 'Allow',
        'rules_priority': 'Priority',
        'rules_active': 'Active',
        'rules_inactive': 'Inactive',
        'rules_save': 'Save',
        'rules_cancel': 'Cancel',
        'rules_delete': 'Delete',
        'rules_delete_confirm': 'Delete rule',
        'rules_empty': 'No filter rules.',
        'rules_type_ip': 'IP Address',
        'rules_type_ip_range': 'IP Range (CIDR)',
        'rules_type_user_agent': 'User-Agent',
        'rules_type_header': 'HTTP Header',
        'rules_type_score': 'Score Threshold',
        'logs_title': 'Verification Logs',
        'logs_all': 'All',
        'logs_successful': 'Successful',
        'logs_blocked': 'Blocked',
        'logs_filtered': 'Filtered',
        'logs_date': 'Date/Time',
        'logs_ip': 'IP Address',
        'logs_ua': 'User-Agent',
        'logs_score': 'Score',
        'logs_status': 'Status',
        'logs_rule': 'Matched Rule',
        'logs_no_data': 'No logs for selected filter',
        'logs_human': 'Human',
        'logs_bot': 'Bot',
        'logs_filter_blocked': 'Filtered',
        'logs_prev': 'Previous',
        'logs_next': 'Next',
        'logs_page': 'Page',
        'logs_filter_ip': 'Filter by IP...',
        'key_public': 'Public Key',
        'key_private': 'Private Key',
        'key_warning': 'Never expose the private key! Use it only on the server side.',
        'copy': 'Copy',
        'copied': 'Copied!',
        'frontend_title': 'JavaScript SDK Integration',
        'frontend_auto_title': 'Option 1: Automatic mode (auto=1, default)',
        'frontend_auto_desc': 'The script will automatically find and protect all forms on the page.',
        'frontend_auto_step': 'Add to <head> or before </body> of your website:',
        'frontend_manual_title': 'Option 2: Manual mode (auto=0)',
        'frontend_manual_desc': 'The script loads and collects data, but does not protect forms automatically.',
        'frontend_manual_step': 'Include the script with auto=0 parameter:',
        'frontend_manual_examples': 'Then protect the forms you need by selector:',
        'frontend_by_id': 'By element ID:',
        'frontend_by_class': 'By CSS class:',
        'frontend_by_selector': 'By any CSS selector:',
        'frontend_note': 'The SDK automatically adds a hidden _antispam_token field to each protected form before submission.',
        'backend_title': 'Server-Side Verification',
        'backend_desc': 'After form submission, your server will receive the _antispam_token field. Send it to our API for verification:',
        'backend_endpoint': 'Verification Endpoint',
        'backend_request': 'Request',
        'backend_response': 'Response',
        'backend_examples': 'Integration Examples',
        'backend_score_note': 'score — rating from 0.0 (bot) to 1.0 (human). Recommended threshold: 0.5',
        'testing_title': 'Test Form',
        'testing_desc': 'Test the antispam integration for this project. Fill out the form and submit — the SDK will collect behavioral signals and generate a token, then the server will verify it.',
        'testing_name': 'Name',
        'testing_email': 'Email',
        'testing_message': 'Message',
        'testing_submit': 'Submit Form',
        'testing_verifying': 'Verifying...',
        'testing_result_human': 'Human detected!',
        'testing_result_bot': 'Bot detected or verification failed.',
        'testing_result_error': 'Verification error',
        'testing_no_token': 'No _antispam_token found. SDK may not have loaded.',
        'stats_title': 'Statistics',
        'stats_total': 'Total Requests',
        'stats_success': 'Successful (Human)',
        'stats_rejected': 'Rejected (Bot)',
        'stats_chart': 'Requests by Day',
        'stats_period': 'Period',
        'stats_7d': '7 days',
        'stats_30d': '30 days',
        'stats_90d': '90 days',
        'stats_no_data': 'No data for selected period',
        'tab_fields': 'Fields',
        'fields_title': 'Custom Fields',
        'fields_add': 'Add Field',
        'fields_name': 'Field Name',
        'fields_empty': 'No custom fields. Add fields to enable log filtering by custom parameters.',
        'fields_delete': 'Delete',
        'fields_delete_confirm': 'Delete field',
        'fields_placeholder': 'e.g. form, method, source',
        'logs_data': 'Data',
        'stats_from': 'From',
        'stats_to': 'To',
        'stats_show': 'Show',
        'stats_custom_range': 'Custom range',
        'logs_all_fields': 'All',
        'back': 'Back',
        'rules_conditions': 'Conditions',
        'rules_add_condition': 'Add Condition',
        'rules_conditions_hint': 'All conditions must match (AND logic)',
        'rules_condition': 'Condition',
    },
}


def get_lang():
    return session.get('lang', 'ru')


def t(key):
    lang = get_lang()
    return TRANSLATIONS.get(lang, {}).get(key, key)


# ── Database ──────────────────────────────────────────────────

def get_db():
    if 'db' not in g:
        if DB_PROVIDER == 'postgres':
            import psycopg2
            import psycopg2.extras
            g.db = psycopg2.connect(
                host=os.environ.get('DB_HOST', 'localhost'),
                port=int(os.environ.get('DB_PORT', '5432')),
                dbname=os.environ.get('DB_NAME', 'antispam'),
                user=os.environ.get('DB_USER', 'antispam'),
                password=os.environ.get('DB_PASSWORD', ''))
            g.db.autocommit = True
        else:
            g.db = sqlite3.connect(DB_PATH)
            g.db.row_factory = sqlite3.Row
            g.db.execute('PRAGMA journal_mode=WAL')
            g.db.execute('PRAGMA foreign_keys=ON')
    return g.db


def db_execute(query, params=None):
    """Execute a query with automatic placeholder translation for PostgreSQL."""
    db = get_db()
    if DB_PROVIDER == 'postgres':
        import psycopg2.extras
        query = query.replace('?', '%s')
        cur = db.cursor(cursor_factory=psycopg2.extras.RealDictCursor)
    else:
        cur = db.cursor()
    cur.execute(query, params or ())
    return cur


def db_fetchone(query, params=None):
    """Execute and fetch one row."""
    cur = db_execute(query, params)
    return cur.fetchone()


def db_fetchall(query, params=None):
    """Execute and fetch all rows."""
    cur = db_execute(query, params)
    return cur.fetchall()


@app.teardown_appcontext
def close_db(exception):
    db = g.pop('db', None)
    if db is not None:
        db.close()


# ── Auth decorator ────────────────────────────────────────────

def login_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if 'user_id' not in session:
            return redirect(url_for('login'))
        return f(*args, **kwargs)
    return decorated


# ── Template context ──────────────────────────────────────────

@app.template_filter('fromjson')
def fromjson_filter(value):
    try:
        return json.loads(value)
    except (ValueError, TypeError):
        return {}


@app.template_filter('fmtdate')
def fmtdate_filter(value):
    """Format datetime or string to 'YYYY-MM-DD HH:MM:SS'."""
    if not value:
        return '-'
    if isinstance(value, str):
        return value[:19]
    try:
        return value.strftime('%Y-%m-%d %H:%M:%S')
    except AttributeError:
        return str(value)[:19]


@app.context_processor
def inject_globals():
    return dict(t=t, lang=get_lang(), session=session)


# ── Routes ────────────────────────────────────────────────────

@app.route('/set-lang/<lang>')
def set_lang(lang):
    if lang in ('ru', 'en'):
        session['lang'] = lang
    return redirect(request.referrer or url_for('login'))


@app.route('/')
@app.route('/login', methods=['GET', 'POST'])
def login():
    if 'user_id' in session:
        return redirect(url_for('projects'))

    error = None
    if request.method == 'POST':
        username = request.form.get('username', '').strip()
        password = request.form.get('password', '').strip()
        user = db_fetchone(
            'SELECT id, username, password_hash FROM users WHERE username = ?',
            (username,)
        )
        if user and bcrypt.checkpw(password.encode(), (user['password_hash'] or '').encode()):
            session['user_id'] = user['id']
            session['username'] = user['username']
            return redirect(url_for('projects'))
        error = t('login_error')

    return render_template('login.html', error=error)


@app.route('/logout')
def logout():
    session.clear()
    return redirect(url_for('login'))


@app.route('/projects')
@login_required
def projects():
    rows = db_fetchall(
        'SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC',
        (session['user_id'],)
    )
    return render_template('projects.html', projects=rows)


@app.route('/projects/create', methods=['POST'])
@login_required
def create_project():
    name = request.form.get('name', '').strip()
    domain = request.form.get('domain', '').strip()
    if name and domain:
        public_key = 'pk_' + uuid.uuid4().hex
        private_key = 'sk_' + uuid.uuid4().hex
        db_execute(
            'INSERT INTO projects (user_id, name, domain, public_key, private_key) VALUES (?, ?, ?, ?, ?)',
            (session['user_id'], name, domain, public_key, private_key)
        )
        if DB_PROVIDER != 'postgres':
            get_db().commit()
    return redirect(url_for('projects'))


@app.route('/projects/<int:project_id>/delete', methods=['POST'])
@login_required
def delete_project(project_id):
    db_execute(
        'DELETE FROM projects WHERE id = ? AND user_id = ?',
        (project_id, session['user_id'])
    )
    if DB_PROVIDER != 'postgres':
        get_db().commit()
    return redirect(url_for('projects'))


@app.route('/projects/<int:project_id>')
@login_required
def detail(project_id):
    project = db_fetchone(
        'SELECT * FROM projects WHERE id = ? AND user_id = ?',
        (project_id, session['user_id'])
    )
    if not project:
        return redirect(url_for('projects'))

    tab = request.args.get('tab', 'keys')
    base_url = request.host_url.rstrip('/')
    return render_template('detail.html', project=project, tab=tab, base_url=base_url)


@app.route('/projects/<int:project_id>/stats')
@login_required
def stats(project_id):
    project = db_fetchone(
        'SELECT * FROM projects WHERE id = ? AND user_id = ?',
        (project_id, session['user_id'])
    )
    if not project:
        return redirect(url_for('projects'))

    days = int(request.args.get('days', 7))
    if days not in (7, 30, 90):
        days = 7

    # Custom date range support
    custom_from = request.args.get('from_date', '').strip()
    custom_to = request.args.get('to_date', '').strip()

    if custom_from and custom_to:
        from_date = custom_from + ' 00:00:00'
        to_date = custom_to + ' 23:59:59'
    else:
        from_date = (datetime.now() - timedelta(days=days)).strftime('%Y-%m-%d 00:00:00')
        to_date = None
        custom_from = ''
        custom_to = ''

    # Load project fields
    project_fields_rows = db_fetchall(
        'SELECT field_name FROM project_fields WHERE project_id = ? ORDER BY id ASC',
        (project_id,)
    )
    project_fields = [r['field_name'] for r in project_fields_rows]

    # Get distinct values for each field
    field_options = {}
    for fname in project_fields:
        rows = db_fetchall(
            '''SELECT DISTINCT lfv.field_value FROM log_field_values lfv
               JOIN verification_logs vl ON vl.id = lfv.log_id
               WHERE vl.project_id = ? AND lfv.field_name = ?
               ORDER BY lfv.field_value''',
            (project_id, fname)
        )
        field_options[fname] = [r['field_value'] for r in rows]

    # Parse field filter params
    field_filters = {}
    for fname in project_fields:
        val = request.args.get('field_' + fname, '').strip()
        if val:
            field_filters[fname] = val

    where = 'WHERE project_id = ? AND created_at >= ?'
    params = [project_id, from_date]
    if to_date:
        where += ' AND created_at <= ?'
        params.append(to_date)

    # Apply field filters via EXISTS subqueries
    for fname, fval in field_filters.items():
        where += ' AND EXISTS (SELECT 1 FROM log_field_values lfv WHERE lfv.log_id = verification_logs.id AND lfv.field_name = ? AND lfv.field_value = ?)'
        params += [fname, fval]

    totals = db_fetchone(
        f'''SELECT COUNT(*) as total,
           SUM(CASE WHEN is_human THEN 1 ELSE 0 END) as success,
           SUM(CASE WHEN NOT is_human THEN 1 ELSE 0 END) as rejected
           FROM verification_logs {where}''',
        params
    )

    daily = db_fetchall(
        f'''SELECT DATE(created_at) as date, COUNT(*) as total,
           SUM(CASE WHEN is_human THEN 1 ELSE 0 END) as success,
           SUM(CASE WHEN NOT is_human THEN 1 ELSE 0 END) as rejected
           FROM verification_logs {where}
           GROUP BY DATE(created_at) ORDER BY date''',
        params
    )

    chart_data = {
        'labels': [str(row['date']) for row in daily],
        'success': [int(row['success'] or 0) for row in daily],
        'rejected': [int(row['rejected'] or 0) for row in daily],
    }

    # Pre-build field_* URL params for template (Jinja2 doesn't support dict comprehensions)
    field_url_params = {'field_' + fn: fv for fn, fv in field_filters.items()}

    return render_template('stats.html', project=project, totals=totals,
                           daily=daily, chart_data=json.dumps(chart_data),
                           days=days, custom_from=custom_from, custom_to=custom_to,
                           field_options=field_options, field_filters=field_filters,
                           field_url_params=field_url_params,
                           project_fields=project_fields)


@app.route('/projects/<int:project_id>/rules', methods=['GET', 'POST'])
@login_required
def rules(project_id):
    project = db_fetchone(
        'SELECT * FROM projects WHERE id = ? AND user_id = ?',
        (project_id, session['user_id'])
    )
    if not project:
        return redirect(url_for('projects'))

    if request.method == 'POST':
        action = request.form.get('action', '')
        if action == 'create' or action == 'update':
            name = request.form.get('name', '').strip()
            rule_action = request.form.get('rule_action', 'block')
            priority = int(request.form.get('priority', 0))
            is_active = 1 if request.form.get('is_active') else 0

            # Collect conditions from form arrays
            condition_types = request.form.getlist('condition_type[]')
            condition_values = request.form.getlist('condition_value[]')
            header_names = request.form.getlist('header_name[]')
            header_values = request.form.getlist('header_value[]')

            # Build final condition list
            conditions = []
            for i, ctype in enumerate(condition_types):
                ctype = ctype.strip()
                if not ctype:
                    continue
                if ctype == 'header':
                    h_name = header_names[i].strip() if i < len(header_names) else ''
                    h_value = header_values[i].strip() if i < len(header_values) else ''
                    cval = json.dumps({'name': h_name, 'value': h_value})
                else:
                    cval = condition_values[i].strip() if i < len(condition_values) else ''
                if cval:
                    conditions.append((ctype, cval))

            if name and conditions:
                if action == 'create':
                    insert_q = 'INSERT INTO filter_rules (project_id, name, rule_type, rule_value, action, is_active, priority) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    if DB_PROVIDER == 'postgres':
                        insert_q += ' RETURNING id'
                    cur = db_execute(insert_q,
                        (project_id, name, '', '', rule_action, is_active, priority)
                    )
                    if DB_PROVIDER == 'postgres':
                        new_rule_id = cur.fetchone()['id']
                    else:
                        new_rule_id = cur.lastrowid
                    for ctype, cval in conditions:
                        db_execute(
                            'INSERT INTO rule_conditions (rule_id, condition_type, condition_value) VALUES (?, ?, ?)',
                            (new_rule_id, ctype, cval)
                        )
                else:
                    rule_id = int(request.form.get('rule_id', 0))
                    db_execute(
                        'UPDATE filter_rules SET name=?, action=?, is_active=?, priority=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND project_id=?',
                        (name, rule_action, is_active, priority, rule_id, project_id)
                    )
                    # Replace conditions: delete old, insert new
                    db_execute('DELETE FROM rule_conditions WHERE rule_id = ?', (rule_id,))
                    for ctype, cval in conditions:
                        db_execute(
                            'INSERT INTO rule_conditions (rule_id, condition_type, condition_value) VALUES (?, ?, ?)',
                            (rule_id, ctype, cval)
                        )
                if DB_PROVIDER != 'postgres':
                    get_db().commit()
        elif action == 'delete':
            rule_id = int(request.form.get('rule_id', 0))
            db_execute('DELETE FROM rule_conditions WHERE rule_id = ?', (rule_id,))
            db_execute('DELETE FROM filter_rules WHERE id = ? AND project_id = ?', (rule_id, project_id))
            if DB_PROVIDER != 'postgres':
                get_db().commit()
        elif action == 'toggle':
            rule_id = int(request.form.get('rule_id', 0))
            db_execute(
                'UPDATE filter_rules SET is_active = CASE WHEN is_active THEN 0 ELSE 1 END, updated_at=CURRENT_TIMESTAMP WHERE id = ? AND project_id = ?',
                (rule_id, project_id)
            )
            if DB_PROVIDER != 'postgres':
                get_db().commit()
        return redirect(url_for('rules', project_id=project_id))

    rules_list = db_fetchall(
        'SELECT * FROM filter_rules WHERE project_id = ? ORDER BY priority DESC, id ASC',
        (project_id,)
    )

    # Batch-load conditions for all rules
    rule_conditions = {}
    if rules_list:
        try:
            rule_ids = [r['id'] for r in rules_list]
            placeholders = ','.join(['?'] * len(rule_ids))
            cond_rows = db_fetchall(
                f'SELECT id, rule_id, condition_type, condition_value FROM rule_conditions WHERE rule_id IN ({placeholders}) ORDER BY id ASC',
                rule_ids
            )
            for c in cond_rows:
                rule_conditions.setdefault(c['rule_id'], []).append(c)
        except Exception:
            pass

    edit_rule = None
    edit_conditions = []
    if request.args.get('edit'):
        edit_rule = db_fetchone(
            'SELECT * FROM filter_rules WHERE id = ? AND project_id = ?',
            (int(request.args.get('edit')), project_id)
        )
        if edit_rule:
            try:
                edit_conditions = db_fetchall(
                    'SELECT id, rule_id, condition_type, condition_value FROM rule_conditions WHERE rule_id = ? ORDER BY id ASC',
                    (edit_rule['id'],)
                )
            except Exception:
                edit_conditions = []

    return render_template('rules.html', project=project, rules=rules_list,
                           rule_conditions=rule_conditions,
                           edit_rule=edit_rule, edit_conditions=edit_conditions,
                           adding=request.args.get('add'))


@app.route('/projects/<int:project_id>/fields', methods=['GET', 'POST'])
@login_required
def fields(project_id):
    project = db_fetchone(
        'SELECT * FROM projects WHERE id = ? AND user_id = ?',
        (project_id, session['user_id'])
    )
    if not project:
        return redirect(url_for('projects'))

    if request.method == 'POST':
        action = request.form.get('action', '')
        if action == 'create':
            field_name = request.form.get('field_name', '').strip()
            if field_name:
                db_execute(
                    'INSERT INTO project_fields (project_id, field_name) VALUES (?, ?)',
                    (project_id, field_name)
                )
                if DB_PROVIDER != 'postgres':
                    get_db().commit()
        elif action == 'delete':
            field_id = int(request.form.get('field_id', 0))
            db_execute(
                'DELETE FROM project_fields WHERE id = ? AND project_id = ?',
                (field_id, project_id)
            )
            if DB_PROVIDER != 'postgres':
                get_db().commit()
        return redirect(url_for('fields', project_id=project_id))

    fields_list = db_fetchall(
        'SELECT * FROM project_fields WHERE project_id = ? ORDER BY id ASC',
        (project_id,)
    )
    return render_template('fields.html', project=project, fields=fields_list)


@app.route('/projects/<int:project_id>/logs')
@login_required
def logs(project_id):
    project = db_fetchone(
        'SELECT * FROM projects WHERE id = ? AND user_id = ?',
        (project_id, session['user_id'])
    )
    if not project:
        return redirect(url_for('projects'))

    status = request.args.get('status', 'all')
    page = max(1, int(request.args.get('page', 1)))
    per_page = 50
    ip_filter = request.args.get('ip', '').strip()

    # Load project fields
    project_fields_rows = db_fetchall(
        'SELECT field_name FROM project_fields WHERE project_id = ? ORDER BY id ASC',
        (project_id,)
    )
    project_fields = [r['field_name'] for r in project_fields_rows]

    # Get distinct values for each field
    field_options = {}
    for fname in project_fields:
        rows = db_fetchall(
            '''SELECT DISTINCT lfv.field_value FROM log_field_values lfv
               JOIN verification_logs vl ON vl.id = lfv.log_id
               WHERE vl.project_id = ? AND lfv.field_name = ?
               ORDER BY lfv.field_value''',
            (project_id, fname)
        )
        field_options[fname] = [r['field_value'] for r in rows]

    # Parse field filter params
    field_filters = {}
    for fname in project_fields:
        val = request.args.get('field_' + fname, '').strip()
        if val:
            field_filters[fname] = val

    where = 'WHERE project_id = ?'
    params = [project_id]
    if status == 'success':
        where += ' AND is_human = ? AND (filter_action IS NULL OR filter_action = ?)'
        params += [True, 'allowed']
    elif status == 'blocked':
        where += ' AND is_human = ? AND filter_action IS NULL'
        params += [False]
    elif status == 'filtered':
        where += ' AND filter_action = ?'
        params += ['blocked']
    if ip_filter:
        where += ' AND ip_address LIKE ?'
        params += ['%' + ip_filter + '%']

    # Apply field filters via EXISTS subqueries
    for fname, fval in field_filters.items():
        where += ' AND EXISTS (SELECT 1 FROM log_field_values lfv WHERE lfv.log_id = verification_logs.id AND lfv.field_name = ? AND lfv.field_value = ?)'
        params += [fname, fval]

    total_row = db_fetchone(f'SELECT COUNT(*) as cnt FROM verification_logs {where}', params)
    total = int(total_row['cnt'] or 0) if total_row else 0
    total_pages = max(1, -(-total // per_page))
    offset = (page - 1) * per_page

    log_rows = db_fetchall(
        f'SELECT id, project_id, token, score, is_human, ip_address, user_agent, signals_summary, filter_action, matched_rule_id, matched_rule_name, custom_data, created_at FROM verification_logs {where} ORDER BY created_at DESC LIMIT {per_page} OFFSET {offset}',
        params
    )

    # Batch load field values for fetched logs
    log_field_values = {}
    if log_rows:
        log_ids = [r['id'] for r in log_rows]
        placeholders = ','.join(['?'] * len(log_ids))
        fv_rows = db_fetchall(
            f'SELECT log_id, field_name, field_value FROM log_field_values WHERE log_id IN ({placeholders})',
            log_ids
        )
        for fv in fv_rows:
            log_field_values.setdefault(fv['log_id'], {})[fv['field_name']] = fv['field_value']

    # Pre-build field_* URL params for template (Jinja2 doesn't support dict comprehensions)
    field_url_params = {'field_' + fn: fv for fn, fv in field_filters.items()}

    return render_template('logs.html', project=project, logs=log_rows,
                           status=status, page=page, total_pages=total_pages,
                           total=total, ip_filter=ip_filter,
                           field_options=field_options, field_filters=field_filters,
                           field_url_params=field_url_params,
                           log_field_values=log_field_values,
                           project_fields=project_fields)


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
