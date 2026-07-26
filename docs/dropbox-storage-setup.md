# Dropbox Cloud Storage — Setup Guide (GenCYMS)

How to connect a Container Yard Management System (GenCYMS) installation to Dropbox
for document storage, and how to onboard each new company. Follow this end to end
and any administrator can reproduce the setup for a new yard.

---

## 1. How it works (read this first)

- **Each GenCYMS installation stores its documents through one active provider.** The
  provider is a single, global setting per installation — Local (default),
  Dropbox, or Google Drive. When Dropbox is active, **every** document uploaded
  in that installation goes to Dropbox.

- **One shared Dropbox app, one folder per company.** We use a single
  Dropbox **"App folder"** app named **GenCYMS**. Dropbox sandboxes it to
  `/Apps/GenCYMS/`. Each company installation writes into its **own subfolder** of
  that app folder, chosen by the **Root Folder** setting in the Yard system:

  ```
  Dropbox
  └── Apps/
      └── GenCYMS/                ← created & owned by the GenCYMS app (one App Key)
          ├── Acme/            ← company 1  (Root Folder = /Acme)
          ├── BluePort/        ← company 2  (Root Folder = /BluePort)
          └── Gateway/         ← company 3  (Root Folder = /Gateway)
  ```

- **Why one app (not one per company):** with "App folder" scope you cannot nest
  several apps' folders inside a single parent folder — each app always gets its
  own `/Apps/<AppName>/`. To get the browsable "open GenCYMS → see every company"
  layout, all companies share the one **GenCYMS** app key and are separated by their
  **Root Folder** subfolder.

- **Authentication is OAuth2 with an auto-refreshing token.** After a one-time
  "Connect with Dropbox", the installation stores a long-lived refresh token and
  renews the short-lived access token automatically. You never paste tokens by
  hand.

---

## 2. Prerequisites

Do these once per installation/server before configuring Dropbox.

1. **Install the Dropbox adapter package** on the server (project root):

   ```bash
   composer require spatie/flysystem-dropbox
   ```

   Without it, the connection test fails with a "run composer require
   spatie/flysystem-dropbox" message.

2. **Confirm the app's public Base URL is correct.** Settings → Company / App
   settings. The Dropbox OAuth callback URL is generated from this, and it must
   exactly match what you register in Dropbox (next section). The site must be
   served over **HTTPS** (Dropbox rejects non-HTTPS redirect URIs, except
   `http://localhost` for local testing).

3. **Admin access.** You need the `settings.cloud-storage.view` and
   `settings.cloud-storage.edit` permissions to see and change these settings.

---

## 3. Part A — Create & configure the Dropbox app (once for all companies)

Do this a single time. All companies reuse the same app.

### 3.1 Create the app

1. Go to **https://www.dropbox.com/developers/apps/create**
   (or **https://www.dropbox.com/developers/apps** → **Create app**).
2. **Choose an API:** select **Scoped access**.
3. **Choose the type of access:** select **App folder**
   (*"Access to a single folder created specifically for your app."*).
4. **Name your app:** type **`GenCYMS`**.
   - App names are **globally unique across all Dropbox developers.** If `GenCYMS`
     is taken, choose another (e.g. `GenCYMS-YourGroup`). **The name becomes the
     folder name** (`/Apps/<name>/`), so pick one you're happy to see as the
     folder — it cannot be cleanly renamed later.
5. Accept the terms and click **Create app**.

> The folder `/Apps/GenCYMS/` is **not** created now. Dropbox creates it the first
> time a company connects (Part B). Do **not** pre-create it by hand — a manual
> folder with the same name causes Dropbox to make `GenCYMS (1)` instead.

### 3.2 Set permissions (scopes)

On the app's **Permissions** tab, enable and then **Submit**:

- `files.metadata.read`
- `files.metadata.write`
- `files.content.read`
- `files.content.write`

### 3.3 Copy the credentials

On the **Settings** tab, note the **App key** and **App secret**. Every company
installation uses this same pair. Keep the App secret confidential.

### 3.4 Register redirect URIs (one per company domain)

On the **Settings** tab → **OAuth 2 → Redirect URIs**, add one entry per company,
using that company's real domain:

```
https://<company-domain>/settings/cloud-storage/dropbox/callback
```

Rules:
- Must be **HTTPS** and an **exact match** (host, path, no trailing slash, no
  query string).
- If a company's app lives under a subpath, include it
  (`https://host/yard/settings/cloud-storage/dropbox/callback`).
- Adding/removing URIs later is safe — it only affects new "Connect" attempts,
  not already-connected installations.

### 3.5 Handle the Development user limit

New apps start in **Development** status. This is fully functional — the only
limit is the number of linked accounts. If you see
*"This app has reached its user limit"*:

1. Settings → **Development users** → click **Enable additional users**.
2. If prompted for a reason: *"Connecting multiple internal container-yard
   installations to our own business Dropbox account."*
3. This is usually granted immediately/quickly and is enough for many companies.

**You can stay in Development indefinitely** for an internal, single-account,
multi-installation deployment like this. Applying for **Production** (Settings →
Status → Apply for production) removes the cap entirely but is *optional* — it is
a review request, not an instant switch, and is only necessary if you expect
hundreds of linked accounts or you let external customers' own Dropbox accounts
link the app.

---

## 4. Part B — Configure & activate in the Yard system (per company)

Repeat for each company installation. Log in as an administrator.

1. Go to **Settings → Cloud Storage**.
2. Select **External storage → Dropbox**.
3. Fill in the fields:
   - **App Key** — the GenCYMS app's key (same for all companies).
   - **App Secret** — the GenCYMS app's secret (same for all companies).
   - **Root Folder** — this company's **unique subfolder**, e.g. `/Acme`.
     - Do **not** use `/GenCYMS` — you are already inside the GenCYMS app folder.
     - Change it from the default `/container-yard` to the company name.
     - Result in Dropbox: `/Apps/GenCYMS/Acme/…`.
4. Click **Save Configuration** (saves credentials without switching the live
   provider yet).
5. Click **Connect with Dropbox** → you're redirected to Dropbox → **Allow**.
   - Authorize with the account that owns the GenCYMS app.
   - On return, the installation stores the refresh token and shows a green
     **Connected** badge. (A **Re-authorize** button appears for later.)
6. Click **Save & Activate External Storage** → Dropbox becomes the active
   provider for all new document uploads in this installation.
7. Click **Test Connection** → writes and deletes a temporary file in the
   company's folder to confirm read/write access. This also **auto-creates the
   subfolder**, so `/Apps/GenCYMS/Acme/` now exists.

> **Legacy access token field:** ignore it. It exists only as a fallback; pasted
> access tokens expire in ~4 hours with no auto-renewal. Always use the OAuth
> "Connect with Dropbox" flow above.

---

## 5. Verify

1. In the Yard system, upload a document (e.g. a survey photo or an estimate
   attachment).
2. In Dropbox, open `/Apps/GenCYMS/<Company>/` and confirm the file appears. Paths
   look like:

   ```
   /Apps/GenCYMS/Acme/surveys/42/9fK3a1..._front-panel.jpg
   ```

3. In the Yard system, open the **Storage Report** — the file is listed and
   counted, categorized by its owning record (Survey, Estimate, Invoice, etc.).

---

## 6. Onboarding a NEW company — quick checklist

**Dropbox side (reuse the one GenCYMS app):**
- [ ] Add the new company's redirect URI:
      `https://<new-domain>/settings/cloud-storage/dropbox/callback`
- [ ] If the user limit is hit: **Enable additional users**.

**New installation side:**
- [ ] `composer require spatie/flysystem-dropbox` installed.
- [ ] App Base URL is correct and HTTPS.
- [ ] Settings → Cloud Storage → Dropbox:
      **App Key / Secret** = GenCYMS app's, **Root Folder** = `/NewCompanyName`.
- [ ] Save Configuration → Connect with Dropbox → Save & Activate → Test
      Connection.
- [ ] Verify a test upload lands in `/Apps/GenCYMS/NewCompanyName/`.

---

## 7. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| *"This app has reached its user limit"* | Development user cap reached | Settings → Development users → **Enable additional users** (or apply for Production). |
| *"redirect_uri did not match"* on connect | Callback URL not registered / mismatch | Register the exact `https://<domain>/settings/cloud-storage/dropbox/callback`; check host, HTTPS, no trailing slash; confirm the app's Base URL matches. |
| Test Connection: "run composer require spatie/flysystem-dropbox" | Package not installed | Run `composer require spatie/flysystem-dropbox` on the server. |
| Files appear in `/Apps/GenCYMS/GenCYMS/…` or `/Apps/GenCYMS/container-yard/…` | Root Folder set to `/GenCYMS` or left at default | Set Root Folder to just the company name, e.g. `/Acme`. |
| Two folders: `GenCYMS` and `GenCYMS (1)` | A manual folder existed before the app created its own | Delete the empty manual folder; let the app own `/Apps/GenCYMS/`. |
| Connected, but no green badge / token missing | OAuth returned without a refresh token | Re-run **Connect with Dropbox**; ensure the app's scopes were submitted. |

---

## 8. Operational notes

- **Only *new* uploads go to Dropbox.** Switching the provider does not move
  files already stored locally. If historical files must move to Dropbox, that is
  a separate migration task.
- **Refresh tokens are automatic.** Once connected, the access token is cached and
  renewed (~every 4 hours) with no manual action.
- **Storage Report & usage ledger still apply.** Dropbox-stored documents are
  recorded in the file-storage ledger at upload time, so they appear in the
  Storage Report and count toward usage, categorized by owning record.
- **`storage:reconcile` and `storage:backfill-ledger` scan the local disk only.**
  They do not walk the Dropbox tree; cloud files are tracked as they are uploaded.
- **Keep the App Secret safe.** It is stored (hidden from API output) and is what
  lets the installation mint new access tokens.

---

## 9. Reference — what the app expects

- **Settings screen:** Settings → Cloud Storage (`settings.cloud-storage.index`).
- **OAuth callback route:** `/settings/cloud-storage/dropbox/callback`.
- **Default Root Folder:** `/container-yard` (override per company).
- **Recommended auth:** App Key + App Secret + OAuth "Connect" (refresh token).
- **Discouraged:** pasted long-lived access token (no auto-refresh).
- **Required package:** `spatie/flysystem-dropbox`.
- **Required scopes:** `files.metadata.read`, `files.metadata.write`,
  `files.content.read`, `files.content.write`.
