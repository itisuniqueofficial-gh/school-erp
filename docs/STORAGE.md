# File storage

* **Local (default):** `storage/` inside the project. Set `STORAGE_PATH=/home/youruser/erp-storage` to keep uploads,
  logs, sessions and backups outside the project and web root. Files are mode 0640 and are only served through
  authorised controllers, never by direct URL.
* **S3-compatible object storage** (AWS S3, Cloudflare R2, MinIO, Backblaze B2, Spaces): set
  `FILESYSTEM_DISK=s3` and `S3_ENDPOINT`, `S3_REGION` (R2: `auto`), `S3_BUCKET`, `S3_KEY`, `S3_SECRET`,
  optional `S3_PATH_STYLE` and `S3_PREFIX`. Requests are signed with AWS Signature V4 using ext-curl (no SDK).
  Objects are private; no public or pre-signed URLs are issued.

Currently routed through the storage layer: **student documents** (the sensitive private files). Each document records
the disk it lives on (`student_documents.storage_disk`), so switching disks never orphans earlier uploads.
Avatars, photos used inside generated PDFs, and branding images still use the local `uploads/` folder.

Installation, upgrades and `db:import` never delete or overwrite files in the storage folder.
Back up the storage folder (or bucket) together with the database: `db:export` covers database rows only.
