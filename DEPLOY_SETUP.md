# GitHub Auto-Deploy Setup

To enable auto-deployment, set these secrets in GitHub repository settings:

1. **DEPLOY_HOST** - Your server IP/domain (e.g., `192.168.1.100` or `example.com`)
2. **DEPLOY_USER** - SSH username (e.g., `root` or `deployer`)
3. **DEPLOY_KEY** - SSH private key (generate with `ssh-keygen -t rsa -b 4096`)
4. **DEPLOY_PATH** - Path to project on server (e.g., `/var/www/mood-checker`)

## How to set it up:

### 1. Generate SSH Key (local machine)
```bash
ssh-keygen -t rsa -b 4096 -f deploy_key -N ""
```

### 2. Add public key to server
```bash
ssh-copy-id -i deploy_key.pub deployer@example.com
```

### 3. Add to GitHub Secrets
- Go to **Settings** → **Secrets and variables** → **Actions**
- Click **New repository secret** for each:
  - `DEPLOY_HOST`: `example.com`
  - `DEPLOY_USER`: `deployer`
  - `DEPLOY_KEY`: (content of `deploy_key` file)
  - `DEPLOY_PATH`: `/var/www/mood-checker`

### 4. Push to main branch
```bash
git push origin main
```

The workflow will automatically deploy!

## Testing
Manually trigger via GitHub UI: **Actions** → **Deploy to Server** → **Run workflow**
