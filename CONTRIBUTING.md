# Contributing

Open a pull request against `main` and describe the WordPress and PHP versions you tested. For publishing changes, include the request path you exercised and the result you observed. Use a disposable WordPress installation for tests that create or delete content, and omit real tokens from issues, logs, and fixtures.

Run `php -l bahricanli-publisher.php` before opening the PR. GitHub Actions checks PHP 8.1 and 8.5 syntax, runs WordPress Plugin Check, and exercises REST publishing in WordPress. Add user-facing changes to the release notes in `readme.txt`.

Contributions are licensed under the GNU General Public License v3.0 or later (`GPL-3.0-or-later`). [License text](LICENSE)
