# AGENTS.md

Канонические инструкции для ВСЕХ кодинг-агентов: Claude Code (через симлинк `CLAUDE.md → AGENTS.md`), Codex, Cursor, Gemini CLI и любых будущих.

duckbug-php — официальный PHP SDK DuckBug (composer-пакет): отправка ошибок и логов в ingest DuckBug.

- Рабочее окружение и проверки — через `make` (docker-compose: `make init` / `make up` / `make down`; полный список целей — в `Makefile`).
- Контракт протокола — репозиторий `duckbug-sdk-spec`; изменения формата событий сверяй с ним и с серверным ingest (`duckbug`), а поведение — с другими SDK (`duckbug-go`, `duckbug-js`).
- Отвечай на русском; код, коммиты и документация — на английском.
- **Без AI-соавторства**: никаких `Co-Authored-By: <ИИ>` в коммитах (для Claude Code продублировано в `.claude/settings.json`).
- **Не коммитить и не пушить без явной команды.**
