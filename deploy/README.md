# Production-only workflow

**Live site:** https://theworldjourney.in/

The old Hostinger preview URL (`khaki-goldfish-975552.hostingersite.com`) is deprecated and redirects to the live domain.

## Deploy changes to live

```bash
./deploy/deploy-live.sh
```

This syncs code to `theworldjourney.in` only. It does **not** publish to the preview URL.

## Git

```bash
git add .
git commit -m "Your message"
GIT_SSH_COMMAND="ssh -i ~/.ssh/github_tourmay" git push origin fin
```

Then run `./deploy/deploy-live.sh`.

## SSH to live server

```bash
ssh theworldjourney
cd domains/theworldjourney.in/public_html
```

`config/database.php` on the live server uses the `u113823106_tour` database and is not in git.
