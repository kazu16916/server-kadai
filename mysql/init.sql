USE voting_app;

-- テーブル定義
CREATE TABLE polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    token VARCHAR(64),
    creator_user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE choices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT,
    text VARCHAR(255),
    votes INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    avatar_path VARCHAR(255) DEFAULT NULL,
    balance INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    choice_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (poll_id, user_id),
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
);

CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    user_id INT NOT NULL,
    choice_id INT DEFAULT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (choice_id) REFERENCES choices(id) ON DELETE CASCADE
);

CREATE TABLE comment_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE attack_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    user_id INT DEFAULT NULL,
    attack_type VARCHAR(50) NOT NULL,
    malicious_input TEXT NOT NULL,
    request_uri VARCHAR(255) NOT NULL,
    user_agent TEXT,
    status_code INT,
    source_type VARCHAR(50) DEFAULT NULL,
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE waf_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    action ENUM('detect', 'block') NOT NULL DEFAULT 'detect',
    is_custom BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ★ 追加: IPアドレスのブロック/モニタリング用テーブル
CREATE TABLE waf_ip_blocklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_pattern VARCHAR(128) NOT NULL UNIQUE,
    action ENUM('block','monitor') NOT NULL DEFAULT 'block',
    description VARCHAR(255) NULL,
    is_custom BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- キーログ（演習用）
CREATE TABLE IF NOT EXISTS keylog_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  occurred_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45) NOT NULL,
  user_agent TEXT,
  field VARCHAR(32) NOT NULL,            -- username / password
  key_code VARCHAR(32) NOT NULL,         -- event.code（例：KeyA, Digit1）
  key_value VARCHAR(16) NOT NULL,        -- 表示文字（パスワードでも1文字ずつ）
  is_password TINYINT(1) NOT NULL DEFAULT 0,
  note VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tamper_baselines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_path VARCHAR(255) NOT NULL UNIQUE,
  sha256 CHAR(64) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS ransom_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payer_user_id INT NOT NULL,
  amount INT NOT NULL,
  status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  confirmed_at DATETIME DEFAULT NULL,
  confirmed_by INT DEFAULT NULL,
  FOREIGN KEY (payer_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- デフォルトの検知ルール (is_custom = FALSE)
INSERT INTO `waf_blacklist` (`pattern`, `description`, `is_custom`) VALUES
-- SQL Injection - Basic Patterns
(''' OR ''=''', 'SQL Injection: Basic Auth Bypass', FALSE),
(''' OR 1=1', 'SQL Injection: Basic Auth Bypass', FALSE),
(''' OR "a"="a', 'SQL Injection: Basic Auth Bypass', FALSE),
(''' OR 1=1--', 'SQL Injection: Auth Bypass with Comment', FALSE),
(''' OR 1=1#', 'SQL Injection: Auth Bypass with Comment', FALSE),
(''' OR 1=1/*', 'SQL Injection: Auth Bypass with Comment', FALSE),
('admin''--', 'SQL Injection: Admin Bypass', FALSE),
('admin''/*', 'SQL Injection: Admin Bypass', FALSE),
(''' OR ''x''=''x', 'SQL Injection: String Bypass', FALSE),
(''' OR 1=1 LIMIT 1--', 'SQL Injection: Bypass with Limit', FALSE),

-- SQL Injection - Advanced Patterns
('UNION SELECT', 'SQL Injection: Union-based', FALSE),
('UNION ALL SELECT', 'SQL Injection: Union All', FALSE),
('UNION+SELECT', 'SQL Injection: Union with Plus', FALSE),
('/**/UNION/**/SELECT', 'SQL Injection: Union with Comments', FALSE),
('/*!UNION*/ /*!SELECT*/', 'SQL Injection: MySQL Version Comment', FALSE),
('UNION(SELECT', 'SQL Injection: Union without Space', FALSE),
('UNION[0x09]SELECT', 'SQL Injection: Union with Hex Tab', FALSE),
('UNION%20SELECT', 'SQL Injection: Union URL Encoded', FALSE),
('UNION%0aselect', 'SQL Injection: Union with Newline', FALSE),
('+UNION+SELECT+', 'SQL Injection: Union with Plus Signs', FALSE),

-- SQL Injection - Time-based Blind
('SLEEP(', 'SQL Injection: Time-based Blind', FALSE),
('sleep(', 'SQL Injection: Time-based Blind (lowercase)', FALSE),
('BENCHMARK(', 'SQL Injection: Benchmark Time Delay', FALSE),
('benchmark(', 'SQL Injection: Benchmark (lowercase)', FALSE),
('WAITFOR DELAY', 'SQL Injection: SQL Server Time Delay', FALSE),
('waitfor delay', 'SQL Injection: SQL Server Time Delay (lowercase)', FALSE),
('pg_sleep(', 'SQL Injection: PostgreSQL Sleep', FALSE),
('DBMS_PIPE.RECEIVE_MESSAGE', 'SQL Injection: Oracle Time Delay', FALSE),

-- SQL Injection - Boolean-based Blind
('AND 1=1', 'SQL Injection: Boolean True', FALSE),
('AND 1=2', 'SQL Injection: Boolean False', FALSE),
('OR 1=1', 'SQL Injection: Boolean True OR', FALSE),
('OR 1=2', 'SQL Injection: Boolean False OR', FALSE),
('AND (SELECT', 'SQL Injection: Boolean Subquery', FALSE),
('OR (SELECT', 'SQL Injection: Boolean OR Subquery', FALSE),
('AND EXISTS(', 'SQL Injection: Boolean EXISTS', FALSE),
('OR EXISTS(', 'SQL Injection: Boolean OR EXISTS', FALSE),

-- SQL Injection - Information Schema
('information_schema', 'SQL Injection: Schema Discovery', FALSE),
('INFORMATION_SCHEMA', 'SQL Injection: Schema Discovery (uppercase)', FALSE),
('information_schema.tables', 'SQL Injection: Table Discovery', FALSE),
('information_schema.columns', 'SQL Injection: Column Discovery', FALSE),
('information_schema.schemata', 'SQL Injection: Database Discovery', FALSE),
('sys.databases', 'SQL Injection: SQL Server Database Discovery', FALSE),
('sysobjects', 'SQL Injection: SQL Server Object Discovery', FALSE),
('syscolumns', 'SQL Injection: SQL Server Column Discovery', FALSE),

-- SQL Injection - MySQL Specific
('@@version', 'SQL Injection: MySQL Version Detection', FALSE),
('@@datadir', 'SQL Injection: MySQL Data Directory', FALSE),
('@@hostname', 'SQL Injection: MySQL Hostname', FALSE),
('@@user', 'SQL Injection: MySQL Current User', FALSE),
('load_file(', 'SQL Injection: MySQL Load File', FALSE),
('LOAD_FILE(', 'SQL Injection: MySQL Load File (uppercase)', FALSE),
('into outfile', 'SQL Injection: MySQL File Write', FALSE),
('INTO OUTFILE', 'SQL Injection: MySQL File Write (uppercase)', FALSE),
('into dumpfile', 'SQL Injection: MySQL Dump File', FALSE),

-- SQL Injection - PostgreSQL Specific
('current_database()', 'SQL Injection: PostgreSQL Current DB', FALSE),
('current_user', 'SQL Injection: PostgreSQL Current User', FALSE),
('version()', 'SQL Injection: PostgreSQL Version', FALSE),
('pg_user', 'SQL Injection: PostgreSQL User Table', FALSE),
('pg_database', 'SQL Injection: PostgreSQL Database Table', FALSE),
('copy (', 'SQL Injection: PostgreSQL Copy Command', FALSE),
('COPY (', 'SQL Injection: PostgreSQL Copy Command (uppercase)', FALSE),

-- SQL Injection - SQL Server Specific
('xp_cmdshell', 'SQL Injection: SQL Server Command Execution', FALSE),
('sp_configure', 'SQL Injection: SQL Server Configuration', FALSE),
('openrowset', 'SQL Injection: SQL Server OpenRowset', FALSE),
('OPENROWSET', 'SQL Injection: SQL Server OpenRowset (uppercase)', FALSE),
('bulk insert', 'SQL Injection: SQL Server Bulk Insert', FALSE),
('BULK INSERT', 'SQL Injection: SQL Server Bulk Insert (uppercase)', FALSE),

-- SQL Injection - Oracle Specific
('dual', 'SQL Injection: Oracle Dual Table', FALSE),
('DUAL', 'SQL Injection: Oracle Dual Table (uppercase)', FALSE),
('user_tables', 'SQL Injection: Oracle User Tables', FALSE),
('all_tables', 'SQL Injection: Oracle All Tables', FALSE),
('dba_tables', 'SQL Injection: Oracle DBA Tables', FALSE),
('utl_http', 'SQL Injection: Oracle UTL_HTTP', FALSE),
('UTL_HTTP', 'SQL Injection: Oracle UTL_HTTP (uppercase)', FALSE),

-- SQL Injection - Comment and Bypass
('--', 'SQL Injection: Comment Out', FALSE),
('/*', 'SQL Injection: Block Comment Start', FALSE),
('*/', 'SQL Injection: Block Comment End', FALSE),
('#', 'SQL Injection: MySQL Comment', FALSE),
('/*!', 'SQL Injection: MySQL Version Comment', FALSE),
('/**/', 'SQL Injection: Empty Comment', FALSE),
(';--', 'SQL Injection: Semicolon Comment', FALSE),
(';#', 'SQL Injection: Semicolon Hash Comment', FALSE),

-- SQL Injection - Encoding and Obfuscation
('CHAR(', 'SQL Injection: Character Encoding', FALSE),
('char(', 'SQL Injection: Character Encoding (lowercase)', FALSE),
('ASCII(', 'SQL Injection: ASCII Conversion', FALSE),
('ascii(', 'SQL Injection: ASCII Conversion (lowercase)', FALSE),
('CONCAT(', 'SQL Injection: String Concatenation', FALSE),
('concat(', 'SQL Injection: String Concatenation (lowercase)', FALSE),
('HEX(', 'SQL Injection: Hex Encoding', FALSE),
('hex(', 'SQL Injection: Hex Encoding (lowercase)', FALSE),
('UNHEX(', 'SQL Injection: Hex Decoding', FALSE),
('unhex(', 'SQL Injection: Hex Decoding (lowercase)', FALSE),

-- XSS - Basic Script Tags
('<script>', 'XSS: Script Tag', FALSE),
('</script>', 'XSS: Script Tag Closing', FALSE),
('<SCRIPT>', 'XSS: Script Tag (uppercase)', FALSE),
('</SCRIPT>', 'XSS: Script Tag Closing (uppercase)', FALSE),
('<script ', 'XSS: Script Tag with Attributes', FALSE),
('<script\t', 'XSS: Script Tag with Tab', FALSE),
('<script\n', 'XSS: Script Tag with Newline', FALSE),
('<script\r', 'XSS: Script Tag with Carriage Return', FALSE),
('<script/>', 'XSS: Self-closing Script Tag', FALSE),

-- XSS - Event Handlers
('onerror=', 'XSS: OnError Event Handler', FALSE),
('onload=', 'XSS: OnLoad Event Handler', FALSE),
('onmouseover=', 'XSS: OnMouseOver Event Handler', FALSE),
('onclick=', 'XSS: OnClick Event Handler', FALSE),
('onfocus=', 'XSS: OnFocus Event Handler', FALSE),
('onblur=', 'XSS: OnBlur Event Handler', FALSE),
('onchange=', 'XSS: OnChange Event Handler', FALSE),
('onsubmit=', 'XSS: OnSubmit Event Handler', FALSE),
('onkeydown=', 'XSS: OnKeyDown Event Handler', FALSE),
('onkeyup=', 'XSS: OnKeyUp Event Handler', FALSE),
('onkeypress=', 'XSS: OnKeyPress Event Handler', FALSE),
('onmousedown=', 'XSS: OnMouseDown Event Handler', FALSE),
('onmouseup=', 'XSS: OnMouseUp Event Handler', FALSE),
('ondblclick=', 'XSS: OnDblClick Event Handler', FALSE),
('oncontextmenu=', 'XSS: OnContextMenu Event Handler', FALSE),

-- XSS - JavaScript Functions
('alert(', 'XSS: Alert Box', FALSE),
('Alert(', 'XSS: Alert Box (capitalized)', FALSE),
('ALERT(', 'XSS: Alert Box (uppercase)', FALSE),
('prompt(', 'XSS: Prompt Box', FALSE),
('Prompt(', 'XSS: Prompt Box (capitalized)', FALSE),
('PROMPT(', 'XSS: Prompt Box (uppercase)', FALSE),
('confirm(', 'XSS: Confirm Box', FALSE),
('Confirm(', 'XSS: Confirm Box (capitalized)', FALSE),
('CONFIRM(', 'XSS: Confirm Box (uppercase)', FALSE),
('eval(', 'XSS: Eval Function', FALSE),
('setTimeout(', 'XSS: SetTimeout Function', FALSE),
('setInterval(', 'XSS: SetInterval Function', FALSE),

-- XSS - Document and Window Objects
('document.cookie', 'XSS: Cookie Theft', FALSE),
('document.write', 'XSS: Document Write', FALSE),
('document.domain', 'XSS: Document Domain', FALSE),
('window.location', 'XSS: Window Location', FALSE),
('location.href', 'XSS: Location Href', FALSE),
('location.replace', 'XSS: Location Replace', FALSE),
('window.open', 'XSS: Window Open', FALSE),
('document.body', 'XSS: Document Body', FALSE),
('document.head', 'XSS: Document Head', FALSE),
('innerHTML', 'XSS: InnerHTML Property', FALSE),
('outerHTML', 'XSS: OuterHTML Property', FALSE),

-- XSS - Protocol Handlers
('javascript:', 'XSS: Javascript Protocol', FALSE),
('JAVASCRIPT:', 'XSS: Javascript Protocol (uppercase)', FALSE),
('vbscript:', 'XSS: VBScript Protocol', FALSE),
('VBSCRIPT:', 'XSS: VBScript Protocol (uppercase)', FALSE),
('data:', 'XSS: Data Protocol', FALSE),
('DATA:', 'XSS: Data Protocol (uppercase)', FALSE),
('mailto:', 'XSS: Mailto Protocol', FALSE),
('tel:', 'XSS: Tel Protocol', FALSE),

-- XSS - HTML Tags
('<iframe>', 'XSS: Iframe Injection', FALSE),
('<IFRAME>', 'XSS: Iframe Injection (uppercase)', FALSE),
('<iframe ', 'XSS: Iframe with Attributes', FALSE),
('<object>', 'XSS: Object Tag', FALSE),
('<OBJECT>', 'XSS: Object Tag (uppercase)', FALSE),
('<embed>', 'XSS: Embed Tag', FALSE),
('<EMBED>', 'XSS: Embed Tag (uppercase)', FALSE),
('<applet>', 'XSS: Applet Tag', FALSE),
('<APPLET>', 'XSS: Applet Tag (uppercase)', FALSE),
('<meta>', 'XSS: Meta Tag', FALSE),
('<META>', 'XSS: Meta Tag (uppercase)', FALSE),
('<link>', 'XSS: Link Tag', FALSE),
('<LINK>', 'XSS: Link Tag (uppercase)', FALSE),
('<style>', 'XSS: Style Tag', FALSE),
('<STYLE>', 'XSS: Style Tag (uppercase)', FALSE),

-- XSS - SVG and Math
('<svg>', 'XSS: SVG Injection', FALSE),
('<SVG>', 'XSS: SVG Injection (uppercase)', FALSE),
('<svg ', 'XSS: SVG with Attributes', FALSE),
('<math>', 'XSS: Math Tag', FALSE),
('<MATH>', 'XSS: Math Tag (uppercase)', FALSE),
('<foreignObject>', 'XSS: ForeignObject Tag', FALSE),
('<animateTransform>', 'XSS: AnimateTransform Tag', FALSE),

-- XSS - Form Elements
('<form>', 'XSS: Form Tag', FALSE),
('<FORM>', 'XSS: Form Tag (uppercase)', FALSE),
('<input>', 'XSS: Input Tag', FALSE),
('<INPUT>', 'XSS: Input Tag (uppercase)', FALSE),
('<textarea>', 'XSS: Textarea Tag', FALSE),
('<TEXTAREA>', 'XSS: Textarea Tag (uppercase)', FALSE),
('<select>', 'XSS: Select Tag', FALSE),
('<SELECT>', 'XSS: Select Tag (uppercase)', FALSE),
('<button>', 'XSS: Button Tag', FALSE),
('<BUTTON>', 'XSS: Button Tag (uppercase)', FALSE),

-- XSS - Encoding and Obfuscation
('&lt;script&gt;', 'XSS: HTML Entity Encoded Script', FALSE),
('&#60;script&#62;', 'XSS: Numeric Entity Encoded Script', FALSE),
('&#x3c;script&#x3e;', 'XSS: Hex Entity Encoded Script', FALSE),
('%3Cscript%3E', 'XSS: URL Encoded Script', FALSE),
('\\u003cscript\\u003e', 'XSS: Unicode Encoded Script', FALSE),
('\\x3cscript\\x3e', 'XSS: Hex Escaped Script', FALSE),

-- Command Injection - Unix/Linux
('; ls', 'OS Command Injection: List Files', FALSE),
('; cat', 'OS Command Injection: Read File', FALSE),
('; whoami', 'OS Command Injection: Get User', FALSE),
('; pwd', 'OS Command Injection: Get Path', FALSE),
('; id', 'OS Command Injection: Get User Info', FALSE),
('; uname', 'OS Command Injection: System Info', FALSE),
('; ps', 'OS Command Injection: Process List', FALSE),
('; netstat', 'OS Command Injection: Network Status', FALSE),
('; ifconfig', 'OS Command Injection: Network Config', FALSE),
('; df', 'OS Command Injection: Disk Usage', FALSE),
('; mount', 'OS Command Injection: Mount Points', FALSE),
('; history', 'OS Command Injection: Command History', FALSE),
('; env', 'OS Command Injection: Environment Variables', FALSE),
('; echo', 'OS Command Injection: Echo Command', FALSE),
('; rm', 'OS Command Injection: Remove Files', FALSE),
('; cp', 'OS Command Injection: Copy Files', FALSE),
('; mv', 'OS Command Injection: Move Files', FALSE),
('; chmod', 'OS Command Injection: Change Permissions', FALSE),
('; chown', 'OS Command Injection: Change Owner', FALSE),
('; find', 'OS Command Injection: Find Files', FALSE),
('; grep', 'OS Command Injection: Search Text', FALSE),
('; awk', 'OS Command Injection: AWK Command', FALSE),
('; sed', 'OS Command Injection: SED Command', FALSE),
('; tail', 'OS Command Injection: Tail Command', FALSE),
('; head', 'OS Command Injection: Head Command', FALSE),
('; less', 'OS Command Injection: Less Command', FALSE),
('; more', 'OS Command Injection: More Command', FALSE),

-- Command Injection - Windows
('; dir', 'OS Command Injection: Windows Directory List', FALSE),
('; type', 'OS Command Injection: Windows Type Command', FALSE),
('; copy', 'OS Command Injection: Windows Copy', FALSE),
('; del', 'OS Command Injection: Windows Delete', FALSE),
('; ren', 'OS Command Injection: Windows Rename', FALSE),
('; move', 'OS Command Injection: Windows Move', FALSE),
('; md', 'OS Command Injection: Windows Make Directory', FALSE),
('; rd', 'OS Command Injection: Windows Remove Directory', FALSE),
('; attrib', 'OS Command Injection: Windows Attributes', FALSE),
('; net user', 'OS Command Injection: Windows User Management', FALSE),
('; net localgroup', 'OS Command Injection: Windows Group Management', FALSE),
('; tasklist', 'OS Command Injection: Windows Task List', FALSE),
('; systeminfo', 'OS Command Injection: Windows System Info', FALSE),
('; ipconfig', 'OS Command Injection: Windows IP Config', FALSE),

-- Command Injection - Command Chaining
('&&', 'OS Command Injection: Command Chaining AND', FALSE),
('||', 'OS Command Injection: Command Chaining OR', FALSE),
('|', 'OS Command Injection: Command Pipe', FALSE),
(';', 'OS Command Injection: Command Separator', FALSE),
('&', 'OS Command Injection: Background Execution', FALSE),
('\n', 'OS Command Injection: Newline Separator', FALSE),
('\r', 'OS Command Injection: Carriage Return', FALSE),

-- Command Injection - Command Substitution
('`', 'OS Command Injection: Command Substitution (Backtick)', FALSE),
('$(', 'OS Command Injection: Command Substitution (Dollar)', FALSE),
('${', 'OS Command Injection: Variable Expansion', FALSE),

-- Command Injection - Network Commands
('nc -l', 'OS Command Injection: Netcat Listener', FALSE),
('nc -e', 'OS Command Injection: Netcat Execute', FALSE),
('netcat', 'OS Command Injection: Netcat', FALSE),
('ncat', 'OS Command Injection: Ncat', FALSE),
('socat', 'OS Command Injection: Socat', FALSE),
('telnet', 'OS Command Injection: Telnet', FALSE),
('ssh', 'OS Command Injection: SSH', FALSE),
('scp', 'OS Command Injection: SCP', FALSE),
('rsync', 'OS Command Injection: Rsync', FALSE),
('wget http', 'OS Command Injection: Wget Download', FALSE),
('curl http', 'OS Command Injection: Curl Download', FALSE),
('curl -X', 'OS Command Injection: Curl Request', FALSE),
('lynx', 'OS Command Injection: Lynx Browser', FALSE),

-- Directory Traversal
('../', 'Directory Traversal: Unix Path', FALSE),
('..\\', 'Directory Traversal: Windows Path', FALSE),
('..../', 'Directory Traversal: Double Dot Slash', FALSE),
('....\\', 'Directory Traversal: Double Dot Backslash', FALSE),
('%2e%2e%2f', 'Directory Traversal: URL Encoded', FALSE),
('%2e%2e%5c', 'Directory Traversal: URL Encoded Windows', FALSE),
('..%2f', 'Directory Traversal: Mixed Encoding', FALSE),
('..%5c', 'Directory Traversal: Mixed Encoding Windows', FALSE),
('%2e%2e/', 'Directory Traversal: Partial URL Encoding', FALSE),
('%2e%2e\\', 'Directory Traversal: Partial URL Encoding Windows', FALSE),

-- Directory Traversal - Sensitive Files Unix
('/etc/passwd', 'Directory Traversal: Unix Password File', FALSE),
('/etc/shadow', 'Directory Traversal: Unix Shadow File', FALSE),
('/etc/group', 'Directory Traversal: Unix Group File', FALSE),
('/etc/hosts', 'Directory Traversal: Unix Hosts File', FALSE),
('/etc/hostname', 'Directory Traversal: Unix Hostname File', FALSE),
('/etc/resolv.conf', 'Directory Traversal: Unix Resolver Config', FALSE),
('/etc/crontab', 'Directory Traversal: Unix Cron Table', FALSE),
('/etc/fstab', 'Directory Traversal: Unix File System Table', FALSE),
('/etc/mtab', 'Directory Traversal: Unix Mount Table', FALSE),
('/etc/issue', 'Directory Traversal: Unix Issue File', FALSE),
('/proc/version', 'Directory Traversal: Linux Version', FALSE),
('/proc/cpuinfo', 'Directory Traversal: Linux CPU Info', FALSE),
('/proc/meminfo', 'Directory Traversal: Linux Memory Info', FALSE),
('/proc/mounts', 'Directory Traversal: Linux Mounts', FALSE),
('/proc/net/arp', 'Directory Traversal: Linux ARP Table', FALSE),

-- Directory Traversal - Sensitive Files Windows
('C:\\windows\\system32\\drivers\\etc\\hosts', 'Directory Traversal: Windows Hosts', FALSE),
('C:\\boot.ini', 'Directory Traversal: Windows Boot Config', FALSE),
('C:\\windows\\win.ini', 'Directory Traversal: Windows INI', FALSE),
('C:\\windows\\system.ini', 'Directory Traversal: Windows System INI', FALSE),
('C:\\windows\\system32\\config\\sam', 'Directory Traversal: Windows SAM', FALSE),
('C:\\windows\\system32\\config\\system', 'Directory Traversal: Windows System Hive', FALSE),
('C:\\windows\\system32\\config\\software', 'Directory Traversal: Windows Software Hive', FALSE),

-- File Inclusion - PHP Wrappers
('php://', 'File Inclusion: PHP Wrapper', FALSE),
('php://filter', 'File Inclusion: PHP Filter', FALSE),
('php://input', 'File Inclusion: PHP Input Stream', FALSE),
('php://memory', 'File Inclusion: PHP Memory Stream', FALSE),
('php://temp', 'File Inclusion: PHP Temp Stream', FALSE),
('data://', 'File Inclusion: Data Wrapper', FALSE),
('file://', 'File Inclusion: File Wrapper', FALSE),
('http://', 'File Inclusion: HTTP Wrapper', FALSE),
('https://', 'File Inclusion: HTTPS Wrapper', FALSE),
('ftp://', 'File Inclusion: FTP Wrapper', FALSE),

-- File Inclusion - Functions
('include(', 'File Inclusion: PHP Include', FALSE),
('include_once(', 'File Inclusion: PHP Include Once', FALSE),
('require(', 'File Inclusion: PHP Require', FALSE),
('require_once(', 'File Inclusion: PHP Require Once', FALSE),
('file_get_contents', 'File Inclusion: PHP File Get Contents', FALSE),
('file_put_contents', 'File Inclusion: PHP File Put Contents', FALSE),
('fopen(', 'File Inclusion: PHP File Open', FALSE),
('fread(', 'File Inclusion: PHP File Read', FALSE),
('fwrite(', 'File Inclusion: PHP File Write', FALSE),
('readfile(', 'File Inclusion: PHP Read File', FALSE),

-- Sensitive File Access
('.env', 'Sensitive File: Environment Variables', FALSE),
('.htaccess', 'Sensitive File: Apache Config', FALSE),
('.htpasswd', 'Sensitive File: Apache Password', FALSE),
('web.config', 'Sensitive File: IIS Config', FALSE),
('wp-config.php', 'Sensitive File: WordPress Config', FALSE),
('config.php', 'Sensitive File: PHP Config', FALSE),
('database.yml', 'Sensitive File: Rails Database Config', FALSE),
('settings.py', 'Sensitive File: Django Settings', FALSE),
('.git', 'Sensitive File: Git Repository', FALSE),
('.svn', 'Sensitive File: SVN Repository', FALSE),
('.hg', 'Sensitive File: Mercurial Repository', FALSE),
('backup.sql', 'Sensitive File: SQL Backup', FALSE),
('dump.sql', 'Sensitive File: SQL Dump', FALSE),
('id_rsa', 'Sensitive File: SSH Private Key', FALSE),
('id_dsa', 'Sensitive File: DSA Private Key', FALSE),
('authorized_keys', 'Sensitive File: SSH Authorized Keys', FALSE),
('known_hosts', 'Sensitive File: SSH Known Hosts', FALSE),

-- Log Files
('access.log', 'Sensitive File: Access Log', FALSE),
('error.log', 'Sensitive File: Error Log', FALSE),
('auth.log', 'Sensitive File: Authentication Log', FALSE),
('secure.log', 'Sensitive File: Security Log', FALSE),
('messages.log', 'Sensitive File: System Messages', FALSE),
('syslog', 'Sensitive File: System Log', FALSE),
('kern.log', 'Sensitive File: Kernel Log', FALSE),
('mail.log', 'Sensitive File: Mail Log', FALSE),

-- Web Shell Signatures - PHP
('system(', 'Web Shell: PHP System Function', FALSE),
('shell_exec(', 'Web Shell: PHP Shell Exec', FALSE),
('passthru(', 'Web Shell: PHP Passthru', FALSE),
('exec(', 'Web Shell: PHP Exec', FALSE),
('popen(', 'Web Shell: PHP Popen', FALSE),
('proc_open(', 'Web Shell: PHP Proc Open', FALSE),
('assert(', 'Web Shell: PHP Assert', FALSE),
('preg_replace', 'Web Shell: PHP Preg Replace', FALSE),
('create_function', 'Web Shell: PHP Create Function', FALSE),
('call_user_func', 'Web Shell: PHP Call User Func', FALSE),

-- Web Shell Signatures - Obfuscation
('base64_decode', 'Web Shell: Base64 Decode Obfuscation', FALSE),
('base64_encode', 'Web Shell: Base64 Encode', FALSE),
('str_rot13', 'Web Shell: ROT13 Obfuscation', FALSE),
('gzinflate', 'Web Shell: GZInflate Obfuscation', FALSE),
('gzuncompress', 'Web Shell: GZUncompress Obfuscation', FALSE),
('gzdecode', 'Web Shell: GZDecode Obfuscation', FALSE),
('rawurldecode', 'Web Shell: Raw URL Decode', FALSE),
('urldecode', 'Web Shell: URL Decode', FALSE),
('hex2bin', 'Web Shell: Hex to Binary', FALSE),
('chr(', 'Web Shell: Character Function', FALSE),
('ord(', 'Web Shell: Ordinal Function', FALSE),

-- Web Shell Signatures - ASP/ASPX
('Server.CreateObject', 'Web Shell: ASP Create Object', FALSE),
('WScript.Shell', 'Web Shell: Windows Script Host', FALSE),
('cmd.exe', 'Web Shell: Windows Command Prompt', FALSE),
('powershell', 'Web Shell: PowerShell', FALSE),
('Response.Write', 'Web Shell: ASP Response Write', FALSE),
('Request.Form', 'Web Shell: ASP Request Form', FALSE),
('ExecuteGlobal', 'Web Shell: VBScript Execute Global', FALSE),
('Eval(', 'Web Shell: ASP Eval', FALSE),

-- Web Shell Signatures - JSP
('Runtime.getRuntime', 'Web Shell: Java Runtime', FALSE),
('ProcessBuilder', 'Web Shell: Java Process Builder', FALSE),
('<%@page import', 'Web Shell: JSP Import', FALSE),
('<%@page language', 'Web Shell: JSP Language', FALSE),
('<%out.print', 'Web Shell: JSP Output', FALSE),
('<%=', 'Web Shell: JSP Expression', FALSE),

-- Web Shell Signatures - Python
('os.system', 'Web Shell: Python OS System', FALSE),
('os.popen', 'Web Shell: Python OS Popen', FALSE),
('subprocess.call', 'Web Shell: Python Subprocess Call', FALSE),
('subprocess.Popen', 'Web Shell: Python Subprocess Popen', FALSE),
('subprocess.run', 'Web Shell: Python Subprocess Run', FALSE),
('__import__', 'Web Shell: Python Import', FALSE),

-- LDAP Injection
('*)(uid=*', 'LDAP Injection: Wildcard UID', FALSE),
('*)(cn=*', 'LDAP Injection: Wildcard CN', FALSE),
('*)(&', 'LDAP Injection: Wildcard AND', FALSE),
('*)(|', 'LDAP Injection: Wildcard OR', FALSE),
('*))%00', 'LDAP Injection: Null Byte', FALSE),
('admin*)((|', 'LDAP Injection: Admin Bypass', FALSE),

-- XML/XXE Injection
('<!ENTITY', 'XXE Injection: Entity Declaration', FALSE),
('<!entity', 'XXE Injection: Entity Declaration (lowercase)', FALSE),
('SYSTEM "file:', 'XXE Injection: System File Access', FALSE),
('system "file:', 'XXE Injection: System File Access (lowercase)', FALSE),
('PUBLIC "-//W3C//DTD', 'XXE Injection: Public DTD', FALSE),
('<!DOCTYPE', 'XXE Injection: DOCTYPE Declaration', FALSE),
('<!doctype', 'XXE Injection: DOCTYPE Declaration (lowercase)', FALSE),
('&xxe;', 'XXE Injection: Entity Reference', FALSE),
('&file;', 'XXE Injection: File Entity Reference', FALSE),

-- XPath Injection
(''' or 1=1 or ''=''', 'XPath Injection: Boolean Bypass', FALSE),
(''' or ''=''', 'XPath Injection: String Bypass', FALSE),
('and 1=1', 'XPath Injection: Boolean True', FALSE),
('and 1=2', 'XPath Injection: Boolean False', FALSE),
('or 1=1', 'XPath Injection: OR True', FALSE),
('or 1=2', 'XPath Injection: OR False', FALSE),
('substring(', 'XPath Injection: Substring Function', FALSE),
('string-length(', 'XPath Injection: String Length Function', FALSE),

-- NoSQL Injection - MongoDB
('$ne', 'NoSQL Injection: MongoDB Not Equal', FALSE),
('$gt', 'NoSQL Injection: MongoDB Greater Than', FALSE),
('$lt', 'NoSQL Injection: MongoDB Less Than', FALSE),
('$regex', 'NoSQL Injection: MongoDB Regex', FALSE),
('$where', 'NoSQL Injection: MongoDB Where', FALSE),
('$or', 'NoSQL Injection: MongoDB OR', FALSE),
('$and', 'NoSQL Injection: MongoDB AND', FALSE),
('$nin', 'NoSQL Injection: MongoDB Not In', FALSE),
('$exists', 'NoSQL Injection: MongoDB Exists', FALSE),

-- HTTP Header Injection
('\r\n', 'HTTP Header Injection: CRLF', FALSE),
('\n', 'HTTP Header Injection: Line Feed', FALSE),
('\r', 'HTTP Header Injection: Carriage Return', FALSE),
('%0d%0a', 'HTTP Header Injection: URL Encoded CRLF', FALSE),
('%0a', 'HTTP Header Injection: URL Encoded LF', FALSE),
('%0d', 'HTTP Header Injection: URL Encoded CR', FALSE),
('Set-Cookie:', 'HTTP Header Injection: Set Cookie', FALSE),
('Location:', 'HTTP Header Injection: Location Header', FALSE),

-- Template Injection - Server Side
('{{', 'Server Side Template Injection: Jinja2/Django', FALSE),
('}}', 'Server Side Template Injection: Jinja2/Django Close', FALSE),
('{%', 'Server Side Template Injection: Jinja2 Block', FALSE),
('%}', 'Server Side Template Injection: Jinja2 Block Close', FALSE),
('${', 'Server Side Template Injection: Spring/JSP EL', FALSE),
('#{', 'Server Side Template Injection: SpEL/JSF', FALSE),
('<%=', 'Server Side Template Injection: ERB/JSP', FALSE),
('%>', 'Server Side Template Injection: ERB/JSP Close', FALSE),
('{{7times7}}', 'Server Side Template Injection: Math Test', FALSE),
('${7times7}', 'Server Side Template Injection: EL Math Test', FALSE),

-- Code Injection - Various Languages
('eval(', 'Code Injection: Eval Function', FALSE),
('exec(', 'Code Injection: Exec Function', FALSE),
('setTimeout(', 'Code Injection: JavaScript SetTimeout', FALSE),
('setInterval(', 'Code Injection: JavaScript SetInterval', FALSE),
('Function(', 'Code Injection: JavaScript Function Constructor', FALSE),
('new Function', 'Code Injection: JavaScript New Function', FALSE),

-- Deserialization Attacks
('O:8:"stdClass"', 'Deserialization: PHP Object', FALSE),
('rO0AB', 'Deserialization: Java Serialized Object', FALSE),
('aced0005', 'Deserialization: Java Magic Bytes', FALSE),
('_pickle', 'Deserialization: Python Pickle', FALSE),
('cPickle', 'Deserialization: Python cPickle', FALSE),

-- Server Side Request Forgery (SSRF)
('http://127.0.0.1', 'SSRF: Localhost IPv4', FALSE),
('http://localhost', 'SSRF: Localhost', FALSE),
('http://0.0.0.0', 'SSRF: All Interfaces', FALSE),
('http://169.254.169.254', 'SSRF: AWS Metadata', FALSE),
('https://169.254.169.254', 'SSRF: AWS Metadata HTTPS', FALSE),
('http://metadata.google.internal', 'SSRF: GCP Metadata', FALSE),
('http://[::1]', 'SSRF: IPv6 Localhost', FALSE),
('file:///', 'SSRF: File Protocol', FALSE),
('gopher://', 'SSRF: Gopher Protocol', FALSE),
('dict://', 'SSRF: Dict Protocol', FALSE),

-- Race Condition Indicators
('LOCK TABLE', 'Race Condition: Table Lock', FALSE),
('UNLOCK TABLE', 'Race Condition: Table Unlock', FALSE),
('BEGIN TRANSACTION', 'Race Condition: Transaction Begin', FALSE),
('COMMIT TRANSACTION', 'Race Condition: Transaction Commit', FALSE),
('ROLLBACK', 'Race Condition: Transaction Rollback', FALSE),

-- Privilege Escalation
('sudo ', 'Privilege Escalation: Sudo Command', FALSE),
('su -', 'Privilege Escalation: Switch User', FALSE),
('chmod +s', 'Privilege Escalation: Set SUID', FALSE),
('chmod 4755', 'Privilege Escalation: SUID Permission', FALSE),
('/etc/sudoers', 'Privilege Escalation: Sudoers File', FALSE),

-- Buffer Overflow Patterns
('AAAAAAAAAA', 'Buffer Overflow: Long String Pattern', FALSE),
('%s%s%s%s', 'Buffer Overflow: Format String', FALSE),
('\\x41\\x41\\x41\\x41', 'Buffer Overflow: Hex Pattern', FALSE),
('AAAA', 'Buffer Overflow: Simple Pattern', FALSE),

-- Crypto/Hash Related
('md5(', 'Crypto Function: MD5 Hash', FALSE),
('sha1(', 'Crypto Function: SHA1 Hash', FALSE),
('sha256(', 'Crypto Function: SHA256 Hash', FALSE),
('crypt(', 'Crypto Function: Crypt', FALSE),
('hash(', 'Crypto Function: Generic Hash', FALSE),

-- Backdoor Signatures
('c99shell', 'Backdoor: C99 Shell', FALSE),
('r57shell', 'Backdoor: R57 Shell', FALSE),
('webshell', 'Backdoor: Generic Web Shell', FALSE),
('b374k', 'Backdoor: B374k Shell', FALSE),
('WSO', 'Backdoor: WSO Shell', FALSE),
('FilesMan', 'Backdoor: Files Manager', FALSE),

-- Network Reconnaissance
('nmap ', 'Network Recon: Nmap Scan', FALSE),
('masscan', 'Network Recon: Masscan', FALSE),
('zmap', 'Network Recon: Zmap', FALSE),
('unicornscan', 'Network Recon: Unicornscan', FALSE),
('hping', 'Network Recon: Hping', FALSE),
('ping -c', 'Network Recon: Ping Count', FALSE),
('traceroute', 'Network Recon: Traceroute', FALSE),
('dig ', 'Network Recon: DNS Lookup', FALSE),
('nslookup', 'Network Recon: NS Lookup', FALSE),
('whois ', 'Network Recon: Whois Query', FALSE),

-- Malware/Virus Signatures
('.scr', 'Malware: Screen Saver Extension', FALSE),
('.pif', 'Malware: Program Information File', FALSE),
('.com', 'Malware: COM Executable', FALSE),
('.bat', 'Malware: Batch File', FALSE),
('.vbs', 'Malware: VBScript File', FALSE),
('.ps1', 'Malware: PowerShell Script', FALSE),

-- Bot/Crawler Detection
('bot', 'Bot Detection: Generic Bot', FALSE),
('crawler', 'Bot Detection: Crawler', FALSE),
('spider', 'Bot Detection: Spider', FALSE),
('scraper', 'Bot Detection: Scraper', FALSE),
('wget/', 'Bot Detection: Wget User Agent', FALSE),
('curl/', 'Bot Detection: Curl User Agent', FALSE),
('python-requests', 'Bot Detection: Python Requests', FALSE),
('libwww-perl', 'Bot Detection: Perl LWP', FALSE),

-- Anti-Debug/VM Evasion
('VirtualBox', 'VM Detection: VirtualBox', FALSE),
('VMware', 'VM Detection: VMware', FALSE),
('QEMU', 'VM Detection: QEMU', FALSE),
('Xen', 'VM Detection: Xen Hypervisor', FALSE),
('Hyper-V', 'VM Detection: Hyper-V', FALSE),

-- Encoding Evasion
('%u0022', 'Encoding Evasion: Unicode Quote', FALSE),
('%u003c', 'Encoding Evasion: Unicode Less Than', FALSE),
('%u003e', 'Encoding Evasion: Unicode Greater Than', FALSE),
('\\u0022', 'Encoding Evasion: JavaScript Unicode Quote', FALSE),
('\\u003c', 'Encoding Evasion: JavaScript Unicode Less Than', FALSE),
('\\u003e', 'Encoding Evasion: JavaScript Unicode Greater Than', FALSE),

-- Generic Suspicious Patterns
('password=', 'Suspicious: Password Parameter', FALSE),
('passwd=', 'Suspicious: Passwd Parameter', FALSE),
('user=admin', 'Suspicious: Admin User', FALSE),
('login=admin', 'Suspicious: Admin Login', FALSE),
('../../../', 'Suspicious: Multiple Directory Traversal', FALSE),
('../../../../', 'Suspicious: Deep Directory Traversal', FALSE),
('../../../../../', 'Suspicious: Very Deep Directory Traversal', FALSE),

-- Protocol Confusion
('HTTP/1.1', 'Protocol: HTTP Version in Data', FALSE),
('GET /', 'Protocol: HTTP GET in Data', FALSE),
('POST /', 'Protocol: HTTP POST in Data', FALSE),
('Host:', 'Protocol: HTTP Host Header in Data', FALSE),
('User-Agent:', 'Protocol: HTTP User Agent in Data', FALSE),

-- Time-based Attack Indicators
('WAITFOR', 'Time Attack: SQL Server Wait', FALSE),
('DELAY', 'Time Attack: MySQL Delay', FALSE),
('pg_sleep', 'Time Attack: PostgreSQL Sleep', FALSE),
('sleep(5)', 'Time Attack: 5 Second Sleep', FALSE),
('sleep(10)', 'Time Attack: 10 Second Sleep', FALSE),

-- Error-based Attack Indicators
('convert(int,', 'Error Attack: SQL Server Convert', FALSE),
('cast(', 'Error Attack: SQL Cast Function', FALSE),
('extractvalue(', 'Error Attack: MySQL ExtractValue', FALSE),
('updatexml(', 'Error Attack: MySQL UpdateXML', FALSE),
('floor(rand(', 'Error Attack: MySQL Floor Rand', FALSE),

-- Blind Injection Indicators
('length(', 'Blind Injection: String Length', FALSE),
('len(', 'Blind Injection: String Length (SQL Server)', FALSE),
('substr(', 'Blind Injection: Substring', FALSE),
('substring(', 'Blind Injection: Substring Function', FALSE),
('mid(', 'Blind Injection: Mid Function', FALSE),
('ascii(', 'Blind Injection: ASCII Function', FALSE),

-- WAF Evasion Techniques
('/*!50000', 'WAF Evasion: MySQL Version Comment', FALSE),
('/*!40000', 'WAF Evasion: MySQL Version Comment', FALSE),
('/*!30000', 'WAF Evasion: MySQL Version Comment', FALSE),
('/**/union/**/', 'WAF Evasion: Comment Union', FALSE),
('uni/**/on', 'WAF Evasion: Broken Union', FALSE),
('sel/**/ect', 'WAF Evasion: Broken Select', FALSE);

-- デフォルトの管理者ユーザーを作成
-- パスワード 'admin' をハッシュ化したものを直接挿入
INSERT INTO users (username, password, role, balance, created_at) VALUES
('admin', 'administrator', 'admin',100, NOW()),
('hanzawa', 'zansin', 'user', 0, NOW());


GRANT ALL PRIVILEGES ON voting_app.* TO 'appuser'@'%';
FLUSH PRIVILEGES;
