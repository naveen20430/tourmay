#!/usr/bin/env bash
# Deploy tour site to https://theworldjourney.in/
# SSH: ssh -p 65002 u113823106@82.112.229.246

set -euo pipefail

SSH_HOST="82.112.229.246"
SSH_PORT="65002"
SSH_USER="u113823106"
SSH_KEY="${HOME}/.ssh/theworldjourney_deploy"
REMOTE_PATH="domains/theworldjourney.in/public_html"
LOCAL_PATH="$(cd "$(dirname "$0")/.." && pwd)"

export GIT_SSH_COMMAND="ssh -i ${SSH_KEY} -p ${SSH_PORT} -o StrictHostKeyChecking=accept-new"

echo "Deploying from: ${LOCAL_PATH}"
echo "Deploying to:   ${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}"
echo ""

rsync -avz --delete \
  -e "ssh -i ${SSH_KEY} -p ${SSH_PORT} -o StrictHostKeyChecking=accept-new" \
  --exclude '.git/' \
  --exclude 'config/database.php' \
  --exclude 'uploads/invoices/' \
  --exclude 'node_modules/' \
  --exclude '.env' \
  --exclude 'tmp_*.txt' \
  --exclude '*.log' \
  "${LOCAL_PATH}/" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}/"

echo ""
echo "Done. Verify: https://theworldjourney.in/"
echo "Note: config/database.php is NOT synced — set DB credentials on the server."
