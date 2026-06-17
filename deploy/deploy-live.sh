#!/usr/bin/env bash
# Deploy to live site only: https://theworldjourney.in/
# Run from the project root after committing changes to git.

set -euo pipefail

SSH_HOST="82.112.229.246"
SSH_PORT="65002"
SSH_USER="u113823106"
SSH_KEY="${HOME}/.ssh/theworldjourney_deploy"
REMOTE_PATH="domains/theworldjourney.in/public_html"
LOCAL_PATH="$(cd "$(dirname "$0")/.." && pwd)"

echo "Deploying to LIVE: https://theworldjourney.in/"
echo "From: ${LOCAL_PATH}"
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
echo "Done. Live site: https://theworldjourney.in/"
echo "Note: config/database.php on the server is never overwritten."
