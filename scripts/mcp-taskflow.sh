#!/bin/sh
cd /Users/bianquiviri/Code/taskflow
export MCP_USER_EMAIL="${MCP_USER_EMAIL:-owner@taskflow.test}"
export APP_ENV="${APP_ENV:-local}"
exec /usr/local/bin/docker compose -f /Users/bianquiviri/Code/taskflow/docker-compose.yml exec -T -e MCP_USER_EMAIL="$MCP_USER_EMAIL" -e APP_ENV="$APP_ENV" app php artisan mcp:serve
