# Local development setup

Getting this repository runnable on a Windows laptop, with Claude Code attached
to it, without committing anything that should not be committed.

Read the first section before running anything. The repository is not shaped the
way a Laravel repository usually is, and that changes the order of the steps.

---

## What this repository actually contains

1,195 files of business code — `app/`, `config/`, `database/`, `resources/`,
`routes/`, `tests/`, `docs/`, part of `public/` — and **none of the Laravel
skeleton**:

| File | In the repo? |
| --- | --- |
| `composer.json`, `composer.lock` | no |
| `artisan` | no |
| `bootstrap/app.php` | no |
| `app/Http/Kernel.php` | no |
| `app/Console/Kernel.php` | no |
| `app/Exceptions/Handler.php` | no |
| `public/index.php` | no |
| `.env.example` | no |
| `package.json` | no |

So a fresh `git clone` of this repository **cannot run**. It has no front
controller, no console entry point, no dependency list. Your laptop and the live
server each hold their own private copy of those files, and nothing compares
them.

Three consequences worth being plain about:

- **The dependency set is not under version control.** When the `--no-dev`
  autoload dump broke the live site, neither of us could check what was actually
  required, because `composer.json` exists nowhere I can see it.
- **A second machine cannot be set up from the repository**, only by copying a
  working directory from an existing one.
- **`git status` on your laptop is unreadable.** `.gitignore` contains one line
  — `.claude/` — so `vendor/`, `node_modules/`, `.env`, `storage/`,
  `bootstrap/` and the rest all show as untracked. Thousands of entries.

### And the CI has never once run

`.github/workflows/tests.yml` is a complete, correct GitHub Actions workflow:
PHP 8.4 with the right extensions, MySQL 8.0, the `container_yard_testing`
database, `composer install`, then the suite. It has **failed on every push ever
recorded** — the last hundred runs, without exception — for one reason:

```
Composer could not find a composer.json file in /home/runner/work/container-yard/container-yard
```

It cannot install dependencies for a project whose dependency list is not in the
repository, so it dies about forty seconds in, every time, and has never reached
a test.

This raises the value of the next few steps considerably. Committing the
skeleton does not only make the repository clonable — **it switches on a test
suite that runs itself on every push**, on Linux, against MySQL 8, without
anybody asking for it. That also catches the class of defect a
case-insensitive laptop filesystem hides and a case-sensitive server does not.

That last one is why **`.gitignore` comes first**. With it in place, `git status`
becomes a short list of exactly the files that should be added, and you can add
them deliberately instead of running `git add .` and hoping.

---

## Step 1 — Write `.gitignore` first

Replace the contents of `.gitignore` in the project root with the following.
Nothing is committed yet; this only changes what `git status` shows you.

```gitignore
# ── Claude Code workspace ───────────────────────────────────────────────
.claude/

# ── Dependencies — restored by composer/npm, never committed ────────────
/vendor/
/node_modules/

# ── Environment and secrets ─────────────────────────────────────────────
# Everything .env* is ignored, then the two examples are let back in.
.env
.env.*
!.env.example
!.env.testing.example

# ── Laravel runtime state, regenerated on demand ────────────────────────
/bootstrap/cache/*
!/bootstrap/cache/.gitkeep

/storage/logs/*
!/storage/logs/.gitkeep
/storage/framework/cache/*
!/storage/framework/cache/.gitkeep
/storage/framework/sessions/*
!/storage/framework/sessions/.gitkeep
/storage/framework/views/*
!/storage/framework/views/.gitkeep
/storage/framework/testing/*
!/storage/framework/testing/.gitkeep
/storage/debugbar/

# Uploaded documents and photos. Real business data — it belongs in the
# filesystem backup, not in git.
/storage/app/*
!/storage/app/.gitkeep
!/storage/app/public/
/storage/app/public/*
!/storage/app/public/.gitkeep

# ── Public ──────────────────────────────────────────────────────────────
# The storage symlink is created per machine by `php artisan storage:link`.
/public/storage
/public/hot
/public/build/

# ── Tooling ─────────────────────────────────────────────────────────────
/.phpunit.result.cache
/.phpunit.cache/
npm-debug.log
yarn-error.log
/.idea/
/.vscode/
*.swp
Thumbs.db
.DS_Store
```

**Do not add anything about `public/images/`.** Gate-movement photo uploads are
already handled by `public/images/gate-movements/.gitignore`, which ignores
everything in that folder except the two `.gitkeep` files. That file is tracked
and working; leave it alone.

Check it took effect:

```bash
git status --short
```

You should now see a manageable list rather than thousands of lines. If `vendor/`
still appears, the `.gitignore` was not saved to the project root.

---

## Step 2 — Compare your laptop against the live server first

This is the step that is easy to skip and expensive to skip. You are about to
make your laptop's copy of these files the official one. If the live server's
copy differs, committing yours and pulling on live **changes live's behaviour**.

Copy these from the live server to a scratch folder and compare each against
your laptop's version:

```
composer.json
composer.lock
artisan
bootstrap/app.php
app/Http/Kernel.php
app/Console/Kernel.php
app/Exceptions/Handler.php
app/Providers/*.php
public/index.php
public/.htaccess
```

On the server:

```bash
cd /var/www/container-yard
tar czf /root/skeleton-$(date +%F).tar.gz \
    composer.json composer.lock artisan bootstrap/app.php \
    app/Http/Kernel.php app/Console/Kernel.php app/Exceptions/Handler.php \
    app/Providers public/index.php public/.htaccess
```

Download that, unpack it somewhere separate, and diff. In PowerShell:

```powershell
Compare-Object (Get-Content .\live\composer.json) (Get-Content .\composer.json)
```

Where they differ, decide which is correct **before** committing. `composer.lock`
is the one to look at hardest: if the server's differs, the two environments are
running different dependency versions, which is worth knowing on its own.

---

## Step 3 — Add the skeleton

Explicit paths, never `git add .` — that is the habit that commits `vendor/` the
one time the ignore rule has a typo.

```bash
git add composer.json composer.lock
git add artisan
git add bootstrap/app.php
git add app/Http/Kernel.php app/Console/Kernel.php app/Exceptions/Handler.php
git add app/Providers/
git add public/index.php
git add public/.htaccess        # if your server uses it
git add package.json package-lock.json vite.config.js   # only if they exist
```

Then the empty directories Laravel needs at runtime. Git does not track
directories, so each needs a `.gitkeep`:

```bash
# PowerShell
"bootstrap/cache","storage/app","storage/app/public","storage/logs",
"storage/framework/cache","storage/framework/sessions",
"storage/framework/views","storage/framework/testing" | ForEach-Object {
    New-Item -ItemType Directory -Force -Path $_ | Out-Null
    New-Item -ItemType File -Force -Path "$_/.gitkeep" | Out-Null
}

git add -f bootstrap/cache/.gitkeep storage/app/.gitkeep storage/app/public/.gitkeep `
          storage/logs/.gitkeep storage/framework/cache/.gitkeep `
          storage/framework/sessions/.gitkeep storage/framework/views/.gitkeep `
          storage/framework/testing/.gitkeep
```

`-f` is required: the ignore rules above exclude those folders' contents, and
the `.gitkeep` files are the deliberate exception.

Review before committing — this is the moment to catch a mistake:

```bash
git status --short
git diff --cached --stat
```

**If that list is longer than about 20 files, stop.** It means something under
`vendor/` or `storage/` has been staged. `git reset` and fix the ignore rules.

```bash
git commit -m "Add the Laravel skeleton so the repository can be cloned and run"
```

---

## Step 4 — `.env.example`

Your `.env` holds database passwords and mail credentials and must never be
committed — the rules above see to that. What should be committed is a copy with
every value blanked, so the next person knows which keys are needed.

```powershell
Get-Content .env | ForEach-Object {
    if ($_ -match '^\s*#' -or $_ -match '^\s*$') { $_ }
    else { ($_ -split '=', 2)[0] + '=' }
} | Set-Content .env.example
```

**Open `.env.example` and read every line before adding it.** The script blanks
`KEY=value`, but a password sitting in a comment, or a value spanning two lines,
will survive. This file goes to a public repository.

```bash
git add .env.example
git commit -m "Document the environment keys a working install needs"
```

---

## Step 5 — Push, then fix the live server before its next pull

```bash
git pull origin claude/reload-container-yard-project-0yXNt
git push -u origin claude/reload-container-yard-project-0yXNt
```

**The live server now has a problem waiting.** It holds untracked copies of
`composer.json`, `artisan`, `app/Http/Kernel.php` and the rest. The next
`git pull` there will refuse:

```
error: The following untracked working tree files would be overwritten by merge:
        composer.json
        artisan
        ...
Please move or remove them before you merge.
```

That refusal is git protecting you, not a fault. Handle it on the server, once:

```bash
cd /var/www/container-yard

# 1. Keep a copy of what is there now.
tar czf /root/skeleton-before-pull-$(date +%F-%H%M).tar.gz \
    composer.json composer.lock artisan bootstrap app/Http/Kernel.php \
    app/Console/Kernel.php app/Exceptions/Handler.php app/Providers \
    public/index.php

# 2. Fetch without merging, and compare the incoming version with what is live.
git fetch origin claude/reload-container-yard-project-0yXNt
git diff FETCH_HEAD -- composer.json composer.lock artisan \
    app/Http/Kernel.php app/Console/Kernel.php app/Exceptions/Handler.php
```

**If that diff is empty, the two are identical** and the pull is safe once the
untracked copies are removed:

```bash
rm -f composer.json composer.lock artisan public/index.php \
      app/Http/Kernel.php app/Console/Kernel.php app/Exceptions/Handler.php
git pull origin claude/reload-container-yard-project-0yXNt
```

**If the diff is not empty, stop and look at it.** The server is running
something different from your laptop, and pulling would replace it. Decide which
version is right, commit that one, and come back.

Afterwards, as always:

```bash
chown -R nginx:nginx storage bootstrap/cache
systemctl restart php-fpm
```

---

## Step 6 — Prove a clone works

The point of all this. In a different folder:

```bash
git clone https://github.com/harshanarw/container-yard.git clone-test
cd clone-test
git checkout claude/reload-container-yard-project-0yXNt

composer install
copy .env.example .env
php artisan key:generate
# fill in the database settings in .env
php artisan migrate
php artisan serve
```

If that serves a login page, the repository is self-contained. Delete the test
clone afterwards.

---

## Step 7 — Claude Code on the laptop

Install Node.js 18 or newer, then:

```bash
npm install -g @anthropic-ai/claude-code
```

Open a terminal **in the project folder** and run:

```bash
claude
```

For the editor integrations — the VS Code extension or the JetBrains plugin for
PhpStorm — follow the current instructions at
<https://code.claude.com/docs>, since the install steps change more often than
this document will.

### Let it run the tests without asking every time

The first few sessions will ask permission for each command. Approving
`php artisan test` permanently is the difference between a fast loop and a slow
one. Create `.claude/settings.local.json` in the project (already ignored by
`.gitignore`):

```json
{
  "permissions": {
    "allow": [
      "Bash(php artisan test:*)",
      "Bash(php artisan migrate:*)",
      "Bash(php artisan route:list:*)",
      "Bash(php -l:*)",
      "Bash(git status:*)",
      "Bash(git diff:*)",
      "Bash(git log:*)"
    ]
  }
}
```

Keep `git push`, `git commit` and anything touching `.env` **out** of that list.
Those should stay deliberate.

---

## Step 8 — `CLAUDE.md`, so a local session starts informed

A fresh local session knows nothing about this project's conventions. Run `/init`
in the first session to generate a starting `CLAUDE.md`, then have it fold in
what is already written down:

- `docs/deployment-seeders.md` — which seeders may run on live and which must
  never, and why
- `docs/container-hire-rental-plan.md` — the three hire relationships
- `docs/gate-correction-plan.md` — the current piece of work
- `docs/live-deploy-hire-rental.md` — the live runbook
- `tests/README.md` — the fixture-naming traps and the workbook-reading rules
- `SEEDER_GUIDE.md`

Worth stating explicitly in `CLAUDE.md`, because they have each cost real time:

```markdown
## Rules

- Never run `php artisan db:seed` or `db:seed --class=RolePermissionSeeder`
  against a live database. `DatabaseSeeder` fabricates business records and
  `RolePermissionSeeder` uses `sync()`, which revokes grants made by hand.
- Tests run against the MySQL `testing` database. The first test in a file costs
  about 70 seconds — that is the migrate-and-seed, not a hang.
- `gate_movements.customer_id` is the *visit* customer. Who is standing at the
  gate is the job's `held_by_customer_id`. They differ on a rental.
- A job creates gate movements only when the container physically moves. A
  lease-in never does.
```

---

## Step 9 — Working in both places

Both environments push to the same branch, so the only rule that matters is
sequencing:

> **Pull before you start. Push before you stop. One environment active at a
> time.**

If you want to work in both at once, give each its own branch for a separate
piece of work — local for the gate corrections, web for Phase 5, say — and merge
deliberately rather than racing on one branch.

Practically: use the web session for planning, for analysing live data you paste
in, and for writing things down. Use the local session for anything that needs
to be **run** — which, going by the record, is where most of the defects have
been found.

---

## macOS (Apple Silicon)

Everything above applies unchanged except the shell. Steps 1–5 — the `.gitignore`,
the comparison against live, adding the skeleton, `.env.example`, the push — are
**done once, from whichever machine already holds the skeleton files**, which is
the Windows laptop. Do them there first.

After that the Mac needs no repository surgery at all. Its setup *is* the clone
test from Step 6, which is the neatest way to prove the fix worked.

### Install what CI says the project needs

`.github/workflows/tests.yml` is the authority, and it asks for PHP **8.4** with
`mbstring, pdo_mysql, bcmath, gd, intl, dom, fileinfo`, and MySQL **8.0**.

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

brew install php@8.4 mysql@8.0 composer node
brew services start mysql@8.0

echo 'export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"' >> ~/.zshrc
echo 'export PATH="/opt/homebrew/opt/mysql@8.0/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc

php -v          # expect 8.4
php -m          # the seven extensions above should all be listed
```

Homebrew's PHP bottle includes most of those; `intl` and `gd` are the two worth
checking in `php -m` before moving on.

### Clone and run

```bash
git clone https://github.com/harshanarw/container-yard.git
cd container-yard
git checkout claude/reload-container-yard-project-0yXNt

composer install
cp .env.example .env
php artisan key:generate
```

Create both databases — the app's, and the one the tests use:

```bash
mysql -u root -e "CREATE DATABASE container_yard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE container_yard_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Point `.env` at the first, and `.env.testing` (copied from
`.env.testing.example`) at the second. Then:

```bash
php artisan migrate --seed
php artisan serve
php artisan test
```

The first test will take about 70 seconds — that is the migrate-and-seed, not a
hang. The full suite runs in roughly four minutes.

### Two differences that matter

**APFS is case-insensitive by default, and so is Windows.** The live server is
not. A Blade view referenced as `yard.gatePass` when the file is `gate-pass`,
or a class in a file whose name differs only in case, works on both your
machines and fails on the server. Neither laptop will ever catch it — the CI
run will, once it can start, because it runs on Ubuntu.

**No `chown` locally.** The `chown -R nginx:nginx storage bootstrap/cache` step
belongs to the server only; on a Mac you own the files already. Running
`php artisan serve` needs no web server configuration at all.

### The PowerShell bits, in zsh

If you do end up doing Steps 1–5 from the Mac instead, these are the equivalents:

```bash
# Step 3 — the .gitkeep files
for d in bootstrap/cache storage/app storage/app/public storage/logs \
         storage/framework/cache storage/framework/sessions \
         storage/framework/views storage/framework/testing; do
    mkdir -p "$d" && touch "$d/.gitkeep"
done

# Step 4 — .env.example, values stripped
sed -E 's/^([A-Za-z_][A-Za-z0-9_]*)=.*/\1=/' .env > .env.example

# Step 2 — comparing against the server
diff live/composer.json composer.json
```

As on Windows: **read `.env.example` line by line before committing it.** The
`sed` blanks `KEY=value` and will not catch a credential sitting in a comment.

---

## Optional — line endings

You are on Windows; the server is Linux. If you start seeing diffs where the
whole file appears changed, add a `.gitattributes`:

```gitattributes
* text=auto eol=lf
```

Do this on its own commit, with nothing else in it, so the renormalisation is
separable from real changes.
