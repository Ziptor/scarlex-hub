<?php
// Returns list of supported AI models for dashboard "Модели" tab.
// Mirrors the catalog shown on the homepage so users can browse from the dashboard.
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}

$models = [
    [
        'family' => 'Anthropic Claude',
        'logo'   => 'claude.png',
        'items'  => [
            ['id' => 'claude-opus-4.7',      'name' => 'Claude Opus 4.7',       'context' => 500000, 'tag' => 'flagship'],
            ['id' => 'claude-opus-4.6',      'name' => 'Claude Opus 4.6',       'context' => 200000, 'tag' => 'reasoning'],
            ['id' => 'claude-sonnet-4.6',    'name' => 'Claude Sonnet 4.6',     'context' => 200000, 'tag' => 'balanced'],
            ['id' => 'claude-sonnet-4.5',    'name' => 'Claude Sonnet 4.5',     'context' => 200000, 'tag' => 'balanced'],
            ['id' => 'claude-haiku-4.5',     'name' => 'Claude Haiku 4.5',      'context' => 200000, 'tag' => 'fast'],
        ],
    ],
    [
        'family' => 'OpenAI GPT',
        'logo'   => 'gpt.png',
        'items'  => [
            ['id' => 'gpt-5.4', 'name' => 'GPT-5.4', 'context' => 400000, 'tag' => 'flagship', 'coming' => true],
            ['id' => 'gpt-5.2', 'name' => 'GPT-5.2', 'context' => 200000, 'tag' => 'balanced', 'coming' => true],
        ],
    ],
    [
        'family' => 'Google Gemini',
        'logo'   => 'gemini.png',
        'items'  => [
            ['id' => 'gemini-3.1-pro-preview', 'name' => 'Gemini 3.1 Pro', 'context' => 1000000, 'tag' => 'long-context', 'coming' => true],
        ],
    ],
];

echo json_encode([
    'ok' => true,
    'models' => $models,
    'base_url' => 'https://api.scarlex.ru',
    'note' => 'Все модели доступны через единый API ключ (OpenAI-совместимый формат /v1/chat/completions и Anthropic /v1/messages). Endpoint: api.scarlex.ru (работает без VPN).',
]);
