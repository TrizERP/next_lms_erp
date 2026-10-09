# Study-deck pictures

Pictures for study decks (and study documents) live in the **database**, in `study_deck_images`. Nothing in the study-deck
pipeline writes a picture to a folder or to the object store.

## Why

Every generation run used to leave files behind: `storage/app/study-deck/chapter-<id>/out/images/` (every picture the run
drew or downloaded, named by hash, so every run added new names), `lms_k12/public/study-deck/chapter-<id>/images/`
(`--export-player` copied the pictures it used and never deleted anything), and `storage/app/study-deck/_image-cache/`
(downloaded sources). Publishing then uploaded the pictures a second time to the object store. For chapter 8592 that was
100 + 43 + 13 files for a deck that uses 10 pictures.

## How it works

| Piece | What it does |
|---|---|
| `study_deck_images` | One row per distinct picture **per school**: the bytes (`MEDIUMBLOB`), type, format, size, width, height, SHA-256, chapter, where it was downloaded from. `(sub_institute_id, sha256)` is unique, so a picture is stored once per school however many runs or decks use it. |
| `study_deck_image_links` | Which stored decks (`content_master.id`) use a picture. `image_id` is a foreign key (cascade). `content_id` is not (a shared legacy table); `lms:unstore-study-deck` removes a deck's links. |
| `StudyDeckImages` | The only code that reads or writes those tables: validation (PNG/JPEG/WebP from the bytes, 8 MiB, 40 MP), `put` (dedupe, race-safe, binary LOB insert), `verify` (read back in slices, recompute SHA-256), visibility, links, leftovers. |
| `StudyImageStores::database($tenant, $chapterId)` | The store the pipeline gives `ImagePlanner`. Returns the picture's reference, `study-deck-image:<id>`. Also remembers a download's address, so a re-run reads it back instead of fetching it again (this replaces the `_image-cache` folder). |
| `StudyDeckPublisher` | At publish time confirms each referenced picture exists and is visible to the deck's school, builds the deck's `assets` map, and (for an old bundle with `images/<name>`) stores the file first. |
| `StudyDeckImageUrls` + `GET /api/study-deck/images/{id}` | Hands a browser a **signed** address (`signed:relative`, the picture id and the school are covered by the signature) and serves the bytes with the stored `Content-Type`, `ETag` = SHA-256 (304 on repeat), `Cache-Control: private`. 404 if there is no such picture, 403 if it is not this school's (or the platform library's, for a school with the LMS). Large pictures are read from the database in 512 KiB slices. |
| PDF / PowerPoint | Read the picture by reference from the database: the PDF embeds a `data:` URI, the presentation takes it from memory (`Drawing\Base64`). No temporary file is made for a picture. |

A deck stored before this change keeps absolute object-store addresses in its deck file; `StudyDeckApiController::show`
passes those through unchanged, so it still plays.

## Who may see a picture

The school that owns it, and the platform library (school 1) for a school that has the LMS (`school_setup.is_lms = 'Y'`)
- the same rule the Classroom Resource list applies to decks. An address is only issued to a caller that passed the deck's
own school check (`POST /api/lms-study-deck`), or to a caller with a token for that school (`POST /api/lms-study-deck/image-urls`,
listed in `config/api_guard.php`). The picture endpoint checks the school again on every request.

## Commands

```
php artisan migrate --path=database/migrations/2026_10_09_160000_create_study_deck_images_tables.php

php artisan study-deck:images-migrate --dry-run --cleanup      # what would be stored / rewritten / deleted
php artisan study-deck:images-migrate                          # store the pictures decks name; rewrite deck.json, images.json, presentation.html to references
php artisan study-deck:images-migrate --cleanup                # delete files whose database copy verifies; remove empty picture folders
php artisan study-deck:images-migrate --include-unreferenced --cleanup   # also store (then delete) files no deck names

php artisan study-deck:images-prune --dry-run                  # pictures no stored deck uses, older than 14 days
php artisan study-deck:images-prune --days=14
```

`study-deck:images-migrate` looks in `storage/app/study-deck` and in the student app's `public/study-deck`
(`STUDY_DECK_PLAYER_DIR`, else the `lms_k12` folder beside this app); pass `--path=` (repeatable) to look elsewhere.
It is idempotent, and it never deletes a file whose database copy has not been read back and matched; a file that no deck
names has no database copy, so it is reported and kept unless `--include-unreferenced` stores it first.

Generating a deck stores its pictures straight away (the review bundle only holds references). A run that is never
stored leaves its pictures behind; `study-deck:images-prune` removes pictures no deck uses.

## Tests

`tests/Unit/StudyDeck/Images/*` run on a throw-away in-memory SQLite built from the real migration
(`Tests\Unit\StudyDeck\Support\UsesImageDatabase`). phpunit.xml points at the shared database, so a test that touches the
picture tables must use that trait; it refuses to run if the switch to SQLite did not take.
