#!/bin/bash
# Push tourmay code to GitHub (SSH deploy key method)
set -e
cd "$(dirname "$0")"

BRANCH="fin"
SSH_KEY="/home/u255007981/.ssh/github_tourmay"
export GIT_SSH_COMMAND="ssh -i ${SSH_KEY} -o StrictHostKeyChecking=accept-new"

git remote set-url origin git@github.com:naveen20430/tourmay.git

echo "Pushing branch '${BRANCH}' to naveen20430/tourmay ..."
git push -u origin "${BRANCH}"

echo ""
echo "Done! View at: https://github.com/naveen20430/tourmay/tree/${BRANCH}"
