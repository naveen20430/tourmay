#!/usr/bin/env bash
# First-time setup on theworldjourney.in server (git clone + database.php)

set -euo pipefail

SSH_HOST="82.112.229.246"
SSH_PORT="65002"
SSH_USER="u113823106"
SSH_KEY="${HOME}/.ssh/theworldjourney_deploy"
REMOTE_PATH="domains/theworldjourney.in/public_html"
REPO="git@github.com:naveen20430/tourmay.git"
BRANCH="fin"

ssh -i "${SSH_KEY}" -p "${SSH_PORT}" -o StrictHostKeyChecking=accept-new \
  "${SSH_USER}@${SSH_HOST}" bash -s <<EOF
set -e
mkdir -p "${REMOTE_PATH}"
cd "${REMOTE_PATH}"
if [ ! -d .git ]; then
  GIT_SSH_COMMAND='ssh -i ~/.ssh/github_tourmay -o StrictHostKeyChecking=accept-new' \\
    git clone -b ${BRANCH} ${REPO} .
else
  git fetch origin
  git checkout ${BRANCH}
  git pull origin ${BRANCH}
fi
if [ ! -f config/database.php ]; then
  cp config/database.example.php config/database.php
  echo "Created config/database.php — edit with theworldjourney.in MySQL credentials."
fi
EOF

echo "Remote setup complete. Edit config/database.php on the server, then open https://theworldjourney.in/"
