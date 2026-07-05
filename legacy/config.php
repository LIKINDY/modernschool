<?php
/**
 * Global app config for AI integrations.
 * Keep this file outside public version control in production.
 */

// Supported values: gemini, openai
define('AI_PROVIDER', getenv('AI_PROVIDER') ?: 'gemini');

// UMEWEKA KEY YAKO HAPA KAMA ULIVYOELEKEZA
define('AI_API_KEY', getenv('AI_API_KEY') ?: '');

// Provider-specific keys (recommended). Gemini falls back to AI_API_KEY.
define('AI_GEMINI_API_KEY', getenv('AI_GEMINI_API_KEY') ?: AI_API_KEY);
define('AI_OPENAI_API_KEY', getenv('AI_OPENAI_API_KEY') ?: '');

// SULUHISHO LA ERROR 429: Tumebadilisha kwenda gemini-1.5-flash
define('AI_MODEL', getenv('AI_MODEL') ?: 'gemini-1.5-flash');
define('AI_GEMINI_MODEL', getenv('AI_GEMINI_MODEL') ?: AI_MODEL);
define('AI_OPENAI_MODEL', getenv('AI_OPENAI_MODEL') ?: 'gpt-4o-mini');

// Optional custom endpoints. Leave empty to use default per provider.
define('AI_GEMINI_BASE_URL', getenv('AI_GEMINI_BASE_URL') ?: 'https://generativelanguage.googleapis.com/v1beta/models');
define('AI_OPENAI_BASE_URL', getenv('AI_OPENAI_BASE_URL') ?: 'https://api.openai.com/v1/chat/completions');
