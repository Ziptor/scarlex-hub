<?php
// Generates ready-to-use config files for various AI clients with the user's API key embedded.
// Usage: GET api/config_download.php?key_id=123&client=claude
//        GET api/config_download.php?action=list (returns metadata for UI)
session_start();
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? 'download';

// ── Client catalog ──
// Each entry: id, name, description, filename, suggested_path, language (json/yaml/toml/env/sh),
// template (with placeholders {{API_KEY}}, {{BASE_URL}}, {{BASE_URL_HOST}}, {{KEY_LABEL}})
$BASE_URL = 'https://api.scarlex.ru';

$CLIENTS = [
    'claude-code' => [
        'name' => 'Claude Code',
        'description' => 'CLI от Anthropic с поддержкой кастомного провайдера через ENV.',
        'filename' => 'settings.json',
        'suggested_path' => '%USERPROFILE%\\.claude\\settings.json  (Win)  ~/.claude/settings.json  (macOS/Linux)',
        'language' => 'json',
        'icon' => '🤖',
        'template' => <<<JSON
{
  "env": {
    "ANTHROPIC_BASE_URL": "{{BASE_URL}}",
    "ANTHROPIC_AUTH_TOKEN": "{{API_KEY}}",
    "ANTHROPIC_MODEL": "claude-sonnet-4.6",
    "ANTHROPIC_SMALL_FAST_MODEL": "claude-haiku-4.5",
    "DISABLE_NON_ESSENTIAL_MODEL_CALLS": "1",
    "CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC": "1"
  }
}
JSON,
    ],
    'opencode' => [
        'name' => 'OpenCode',
        'description' => 'Universal AI coding agent (opencode.ai). Полный конфиг со всеми моделями Scarlex.',
        'filename' => 'opencode.json',
        'suggested_path' => 'opencode.json в корне проекта  ИЛИ  ~/.config/opencode/opencode.json',
        'language' => 'json',
        'icon' => '⚡',
        'template' => <<<JSON
{
  "\$schema": "https://opencode.ai/config.json",
  "model": "Scarlex/claude-opus-4.7",
  "provider": {
    "Scarlex": {
      "npm": "@ai-sdk/openai-compatible",
      "name": "Scarlex",
      "options": {
        "baseURL": "{{BASE_URL}}/v1",
        "apiKey": "{{API_KEY}}"
      },
      "models": {
        "claude-opus-4.7":      { "name": "Claude Opus 4.7",      "limit": { "context": 500000,  "output": 64000 } },
        "claude-opus-4.6":      { "name": "Claude Opus 4.6",      "limit": { "context": 200000,  "output": 64000 }, "variants": { "high": {}, "medium": {}, "low": {} } },
        "claude-sonnet-4.6":    { "name": "Claude Sonnet 4.6",    "limit": { "context": 200000,  "output": 64000 } },
        "claude-sonnet-4.5":    { "name": "Claude Sonnet 4.5",    "limit": { "context": 200000,  "output": 64000 } },
        "claude-haiku-4.5":     { "name": "Claude Haiku 4.5",     "limit": { "context": 200000,  "output": 32000 } },
        "gpt-5.4":              { "name": "GPT-5.4",              "limit": { "context": 400000,  "output": 128000 }, "options": { "include": ["reasoning.encrypted_content"] }, "variants": { "low": {}, "medium": {}, "high": {}, "xhigh": {} } },
        "gpt-5.2":              { "name": "GPT-5.2",              "limit": { "context": 200000,  "output": 64000 } },
        "gemini-3.1-pro-preview": { "name": "Gemini 3.1 Pro",     "limit": { "context": 1000000, "output": 65536 } }
      }
    }
  }
}
JSON,
    ],
    'cursor' => [
        'name' => 'Cursor',
        'description' => 'Cursor IDE → Settings → Models → OpenAI API Key + Override OpenAI Base URL.',
        'filename' => 'cursor-setup.txt',
        'suggested_path' => 'Cursor → Cmd/Ctrl+, → Models → Override OpenAI Base URL',
        'language' => 'text',
        'icon' => '🖱',
        'template' => <<<TXT
=== Настройка Cursor для работы со Scarlex ===

1. Откройте Cursor → Settings (Cmd/Ctrl + ,)
2. Перейдите в раздел "Models"
3. Включите "Override OpenAI Base URL" и вставьте:

   {{BASE_URL}}/v1

4. В поле "OpenAI API Key" вставьте:

   {{API_KEY}}

5. Нажмите "Verify" — Cursor проверит ключ.
6. В списке моделей добавьте кастомные:

   claude-opus-4.7
   claude-sonnet-4.6
   claude-haiku-4.5
   gpt-5.4
   gpt-5.2
   gemini-3.1-pro-preview

Готово! Cursor будет использовать ваш Scarlex-ключ.
TXT,
    ],
    'cline' => [
        'name' => 'Cline (VS Code)',
        'description' => 'Cline / Roo Code — расширение для VS Code с поддержкой OpenAI Compatible API.',
        'filename' => 'cline-setup.txt',
        'suggested_path' => 'VS Code → Cline → Settings → API Provider: OpenAI Compatible',
        'language' => 'text',
        'icon' => '🧩',
        'template' => <<<TXT
=== Настройка Cline / Roo Code для Scarlex ===

1. Откройте Cline в VS Code
2. Settings (шестерёнка) → API Provider: "OpenAI Compatible"
3. Заполните поля:

   Base URL:  {{BASE_URL}}/v1
   API Key:   {{API_KEY}}
   Model ID:  claude-sonnet-4.6

4. (Опционально) Включите "Use Custom Base URL" если поле скрыто.
5. Сохраните и протестируйте — отправьте любое сообщение.

Доступные модели:
  claude-opus-4.7, claude-opus-4.6, claude-sonnet-4.6,
  claude-sonnet-4.5, claude-haiku-4.5,
  gpt-5.4, gpt-5.2, gemini-3.1-pro-preview
TXT,
    ],
    'continue' => [
        'name' => 'Continue',
        'description' => 'Continue.dev — расширение для VS Code/JetBrains. Полный config.json.',
        'filename' => 'config.json',
        'suggested_path' => '~/.continue/config.json',
        'language' => 'json',
        'icon' => '↪',
        'template' => <<<JSON
{
  "models": [
    {
      "title": "Scarlex · Claude Opus 4.7",
      "provider": "openai",
      "model": "claude-opus-4.7",
      "apiBase": "{{BASE_URL}}/v1",
      "apiKey": "{{API_KEY}}"
    },
    {
      "title": "Scarlex · Claude Sonnet 4.6",
      "provider": "openai",
      "model": "claude-sonnet-4.6",
      "apiBase": "{{BASE_URL}}/v1",
      "apiKey": "{{API_KEY}}"
    },
    {
      "title": "Scarlex · GPT-5.4",
      "provider": "openai",
      "model": "gpt-5.4",
      "apiBase": "{{BASE_URL}}/v1",
      "apiKey": "{{API_KEY}}"
    },
    {
      "title": "Scarlex · Gemini 3.1 Pro",
      "provider": "openai",
      "model": "gemini-3.1-pro-preview",
      "apiBase": "{{BASE_URL}}/v1",
      "apiKey": "{{API_KEY}}"
    }
  ],
  "tabAutocompleteModel": {
    "title": "Scarlex · Haiku (autocomplete)",
    "provider": "openai",
    "model": "claude-haiku-4.5",
    "apiBase": "{{BASE_URL}}/v1",
    "apiKey": "{{API_KEY}}"
  }
}
JSON,
    ],
    'aider' => [
        'name' => 'Aider',
        'description' => 'Aider — AI pair programming в терминале. Конфиг + env-переменные.',
        'filename' => '.aider.conf.yml',
        'suggested_path' => '~/.aider.conf.yml  +  переменные среды',
        'language' => 'yaml',
        'icon' => '🛠',
        'template' => <<<YAML
# ~/.aider.conf.yml
# Scarlex API через OpenAI-совместимый интерфейс

openai-api-base: {{BASE_URL}}/v1
openai-api-key: {{API_KEY}}

# Модель по умолчанию (можно переопределить флагом --model)
model: openai/claude-sonnet-4.6

# Weak model — для коммит-сообщений и побочных задач
weak-model: openai/claude-haiku-4.5

# Альтернативно — через ENV (добавьте в ~/.bashrc / ~/.zshrc):
#   export OPENAI_API_BASE="{{BASE_URL}}/v1"
#   export OPENAI_API_KEY="{{API_KEY}}"
YAML,
    ],
    'oh-my-opencode' => [
        'name' => 'oh-my-opencode',
        'description' => 'Plugin для OpenCode с мульти-агентами и категориями задач.',
        'filename' => 'opencode.json',
        'suggested_path' => 'opencode.json в корне проекта',
        'language' => 'json',
        'icon' => '🔌',
        'template' => <<<JSON
{
  "\$schema": "https://opencode.ai/config.json",
  "model": "Scarlex/claude-opus-4.7",
  "plugin": ["oh-my-opencode"],
  "provider": {
    "Scarlex": {
      "npm": "@ai-sdk/openai-compatible",
      "name": "Scarlex",
      "options": {
        "baseURL": "{{BASE_URL}}/v1",
        "apiKey": "{{API_KEY}}"
      },
      "models": {
        "claude-opus-4.7":   { "name": "Claude Opus 4.7",   "limit": { "context": 500000, "output": 64000 } },
        "claude-sonnet-4.6": { "name": "Claude Sonnet 4.6", "limit": { "context": 200000, "output": 64000 } },
        "claude-haiku-4.5":  { "name": "Claude Haiku 4.5",  "limit": { "context": 200000, "output": 32000 } },
        "gpt-5.4":           { "name": "GPT-5.4",           "limit": { "context": 400000, "output": 128000 } },
        "gemini-3.1-pro-preview": { "name": "Gemini 3.1 Pro", "limit": { "context": 1000000, "output": 65536 } }
      }
    }
  }
}
JSON,
    ],
    'zed' => [
        'name' => 'Zed Editor',
        'description' => 'Zed — быстрый редактор от создателей Atom. AI ассистент через OpenAI-совместимый API.',
        'filename' => 'settings.json',
        'suggested_path' => '~/.config/zed/settings.json  ИЛИ  Cmd/Ctrl + , в Zed',
        'language' => 'json',
        'icon' => '⚡',
        'template' => <<<JSON
{
  "language_models": {
    "openai": {
      "version": "1",
      "api_url": "{{BASE_URL}}/v1",
      "available_models": [
        { "name": "claude-opus-4.7",         "display_name": "Claude Opus 4.7",   "max_tokens": 500000  },
        { "name": "claude-sonnet-4.6",       "display_name": "Claude Sonnet 4.6", "max_tokens": 200000  },
        { "name": "claude-haiku-4.5",        "display_name": "Claude Haiku 4.5",  "max_tokens": 200000  },
        { "name": "gpt-5.4",                 "display_name": "GPT-5.4",            "max_tokens": 400000  },
        { "name": "gemini-3.1-pro-preview",  "display_name": "Gemini 3.1 Pro",    "max_tokens": 1000000 }
      ]
    }
  },
  "assistant": {
    "default_model": { "provider": "openai", "model": "claude-sonnet-4.6" },
    "version": "2"
  }
}
JSON,
    ],
    'codex' => [
        'name' => 'OpenAI Codex CLI',
        'description' => 'Официальный Codex CLI от OpenAI (npm package @openai/codex).',
        'filename' => '.codex.toml',
        'suggested_path' => '~/.codex/config.toml  +  ENV',
        'language' => 'toml',
        'icon' => '⌨',
        'template' => <<<TOML
# ~/.codex/config.toml
# Scarlex как кастомный провайдер для Codex CLI

[providers.scarlex]
name = "Scarlex"
base_url = "{{BASE_URL}}/v1"
api_key = "{{API_KEY}}"

[default]
provider = "scarlex"
model = "gpt-5.4"

# Альтернативно через ENV:
#   export OPENAI_BASE_URL="{{BASE_URL}}/v1"
#   export OPENAI_API_KEY="{{API_KEY}}"
TOML,
    ],
    'env' => [
        'name' => 'ENV (.env)',
        'description' => 'Универсальные переменные среды для любого OpenAI-совместимого SDK (Python, Node, Go).',
        'filename' => '.env',
        'suggested_path' => '.env в корне проекта',
        'language' => 'env',
        'icon' => '🔐',
        'template' => <<<ENV
# Scarlex API — OpenAI-совместимый
OPENAI_API_BASE={{BASE_URL}}/v1
OPENAI_BASE_URL={{BASE_URL}}/v1
OPENAI_API_KEY={{API_KEY}}

# Anthropic-совместимый (Claude Code и пр.)
ANTHROPIC_BASE_URL={{BASE_URL}}
ANTHROPIC_AUTH_TOKEN={{API_KEY}}
ANTHROPIC_API_KEY={{API_KEY}}
ENV,
    ],
    'shell' => [
        'name' => 'Shell (export)',
        'description' => 'Готовый блок для ~/.bashrc / ~/.zshrc / PowerShell профиля.',
        'filename' => 'scarlex-env.sh',
        'suggested_path' => '. ~/.bashrc  ИЛИ  source ~/.zshrc',
        'language' => 'sh',
        'icon' => '🐚',
        'template' => <<<SH
# === Scarlex API ===
# Добавьте в ~/.bashrc или ~/.zshrc
export SCARLEX_API_KEY="{{API_KEY}}"

# OpenAI-совместимые SDK
export OPENAI_API_BASE="{{BASE_URL}}/v1"
export OPENAI_BASE_URL="{{BASE_URL}}/v1"
export OPENAI_API_KEY="\$SCARLEX_API_KEY"

# Anthropic / Claude Code
export ANTHROPIC_BASE_URL="{{BASE_URL}}"
export ANTHROPIC_AUTH_TOKEN="\$SCARLEX_API_KEY"
export ANTHROPIC_API_KEY="\$SCARLEX_API_KEY"

# PowerShell-эквивалент:
#   \$env:OPENAI_API_KEY = "{{API_KEY}}"
#   \$env:OPENAI_BASE_URL = "{{BASE_URL}}/v1"
SH,
    ],
    'openai-py' => [
        'name' => 'Python (OpenAI SDK)',
        'description' => 'Готовый сниппет для openai-python (`pip install openai`).',
        'filename' => 'scarlex_example.py',
        'suggested_path' => 'Любой Python проект',
        'language' => 'python',
        'icon' => '🐍',
        'template' => <<<PY
# pip install openai
from openai import OpenAI

client = OpenAI(
    base_url="{{BASE_URL}}/v1",
    api_key="{{API_KEY}}",
)

resp = client.chat.completions.create(
    model="claude-sonnet-4.6",
    messages=[{"role": "user", "content": "Привет, Scarlex!"}],
)
print(resp.choices[0].message.content)
PY,
    ],
    'openai-node' => [
        'name' => 'Node.js (OpenAI SDK)',
        'description' => 'Готовый сниппет для openai-node (`npm i openai`).',
        'filename' => 'scarlex-example.mjs',
        'suggested_path' => 'Любой Node.js проект',
        'language' => 'javascript',
        'icon' => '🟢',
        'template' => <<<JS
// npm i openai
import OpenAI from "openai";

const client = new OpenAI({
  baseURL: "{{BASE_URL}}/v1",
  apiKey: "{{API_KEY}}",
});

const resp = await client.chat.completions.create({
  model: "claude-sonnet-4.6",
  messages: [{ role: "user", content: "Привет, Scarlex!" }],
});

console.log(resp.choices[0].message.content);
JS,
    ],
];

// ── Action: list (metadata for UI) ──
if ($action === 'list') {
    header('Content-Type: application/json');
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
        exit;
    }
    $list = [];
    foreach ($CLIENTS as $id => $c) {
        $list[] = [
            'id' => $id,
            'name' => $c['name'],
            'description' => $c['description'],
            'filename' => $c['filename'],
            'suggested_path' => $c['suggested_path'],
            'language' => $c['language'],
            'icon' => $c['icon'],
        ];
    }
    echo json_encode(['ok' => true, 'clients' => $list, 'base_url' => $BASE_URL]);
    exit;
}

// ── Action: download (default) ──
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Не авторизован';
    exit;
}

$userId = (int)$_SESSION['user_id'];
$clientId = $_GET['client'] ?? '';
$keyId = (int)($_GET['key_id'] ?? 0);
$mode = $_GET['mode'] ?? 'download'; // download | preview (returns JSON with text)

if (!isset($CLIENTS[$clientId])) {
    http_response_code(400);
    echo 'Unknown client';
    exit;
}
if (!$keyId) {
    http_response_code(400);
    echo 'key_id required';
    exit;
}

// Verify key belongs to user
$stmt = $pdo->prepare('SELECT id, key_value, label FROM api_keys WHERE id = ? AND user_id = ?');
$stmt->execute([$keyId, $userId]);
$key = $stmt->fetch();
if (!$key) {
    http_response_code(404);
    echo 'Key not found';
    exit;
}

$client = $CLIENTS[$clientId];
$content = strtr($client['template'], [
    '{{API_KEY}}'        => $key['key_value'],
    '{{BASE_URL}}'       => $BASE_URL,
    '{{BASE_URL_HOST}}'  => 'api.scarlex.ru',
    '{{KEY_LABEL}}'      => $key['label'] ?: 'My Scarlex Key',
]);

if ($mode === 'preview') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'filename' => $client['filename'],
        'language' => $client['language'],
        'content' => $content,
        'suggested_path' => $client['suggested_path'],
    ]);
    exit;
}

// Download
$mime = [
    'json' => 'application/json',
    'yaml' => 'application/x-yaml',
    'toml' => 'application/toml',
    'env' => 'text/plain',
    'sh' => 'application/x-sh',
    'python' => 'text/x-python',
    'javascript' => 'application/javascript',
    'text' => 'text/plain',
][$client['language']] ?? 'text/plain';

header('Content-Type: ' . $mime . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $client['filename'] . '"');
header('Content-Length: ' . strlen($content));
echo $content;
