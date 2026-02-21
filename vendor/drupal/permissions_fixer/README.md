# Script to fix permissions in a Drupal installation

This script sets the permissions and ownership of the files of a Drupal installation.

This is loosely based on the information provided by the Drupal documentation page "[Securing file permissions and ownership](https://www.drupal.org/node/244924)".

## Details

For security reasons, the code files of a website should not be writable. At the same time, the website should be able to create files (for example, when a user uploads an image). This means that there are two types of files and folders: content and code.

There will be two users involved: a regular UNIX user, we'll call them the deploy user, that is in charge of managing the code (typically deploying new releases), and the user under which the web server process is running.

This script tries to secure the site using the following scheme:

  - Code is owned by the deploy user and by the web server's group. Deploy user can write, web server group only read.

  - Content is owned using the same scheme but the web server can write as well.

  - Other users have no permissions on content or code.


In UNIX terms:

|                     |  Symbolic notation |  Numeric notation | ls notation  |
|-------------------- |------------------- |------------------ |------------- |
| **Code folders**    |    `u=rwx,g=rx,o=` |            `0750` |  `rwxr-x---` |
| **Code files**      |      `u=rw,g=r,o=` |            `0640` |  `rw-r-----` |
| **Content folders** |   `u=rwx,g=rwx,o=` |            `0770` |  `rwxrwx---` |
| **Content files**   |         `ug=rw,o=` |            `0660` |  `rw-rw----` |


### Autofix script

A `autofix-drupal-permissions` script is provided as a wrapper to invoke the main `fix-drupal-permissions` script with predefined parameters. This is very useful because it avoids to remember all the parameters each time you want to run the script.

Also, it allows to configure it in sudoers easily avoiding security issues, because allowing a user to run `fix-drupal-permissions` with arbitrary parameters could be a security risk.

### Root privileges

In order to manipulate files/folders ownership and permissions the script must be run as root.

You may need to configure your sudoers file to allow for that. An example is included in `autofix-drupal-permissions.sudoers.example` file.

## Installation

### Via Composer (Recommended)

Install the package using Composer:

```bash
composer require drupal/permissions-fixer
```

The scripts will be installed in `vendor/bin/` and will be automatically available in your PATH when working within your project directory.

After installation, you can run the scripts directly:

```bash
# Run the main script
sudo vendor/bin/fix-drupal-permissions -u=deploy

# Or use the autofix wrapper
sudo vendor/bin/autofix-drupal-permissions
```

### Manual installation

Alternatively, you can clone or download the repository content to your server.

### What is provided

Two scripts are provided:

 * `fix-drupal-permissions`: Main script that actually does the work
 * `autofix-drupal-permissions`: Wrapper to invoke `fix-drupal-permissions`
  with predefined parameters. It is handy to configure in sudoers.

If `autofix-drupal-permissions` fits your needs just symlink it into `/usr/local/bin` or another location reachable from the user's PATH.

Otherwise, you can modify it as you need, create your own wrapper or invoke `fix-drupal-permissions` directly.

## Usage

Check the script help for details in usage:

```
fix-drupal-permissions -h
```

The script should be run as root because it needs to change ownership and only root can do this freely.

Example:
```bash
# If installed via Composer
sudo vendor/bin/fix-drupal-permissions -u=deploy

# If installed manually
sudo fix-drupal-permissions -u=deploy
```

This will fix the permissions of a Drupal installation located in the current folder and using `deploy` as the deploy user.


## Strategy

The script checks if the target folder is a Drupal installation and stops if it is not detected.

Once checked, it fixes the ownership of all folders and files (because it is the same for content and code). Then, it fixes the code files and later the content
files.

The script assumes that `files` and `private` folders under `sites` are content folders.

If there are content folders outside the Drupal root folder you can use the `--files-path` option and the script will take care of them.

## Vendor folder

If a `vendor` folder and a `composer.json` file are detected in the parent folder of the Drupal root the script assumes the `vendor` folder is a code folder and fixes permissions accordingly: it fixes ownership (owner: deploy user, group: web server) and removes any permissions for other users.

It doesn't apply standard permissions of code files because in `vendor` folders some files need to be executable. It would be hard to detect all the cases that need executable permissions so the script doesn't handle permissions for the owner or the group and just removes all permissions for other users.

In case of issues in the `vendor` folder, because the the script fixes ownership on the `vendor` folder, the deploy user should able to run `composer install` and let composer set the correct permissions. Later, the script can be run again to remove all permissions on other users.

## Performance

The script only changes the files and folders with the wrong permissions or ownership, making it very fast when only a few files or folders need a fix. For really large installations this is very important as other scripts update permissions and ownership regardless of whether they are needed or not.

However, checking each file's current state has overhead. When most or all files need fixing (e.g., initial setup or after bulk changes), this checking can add 10~30% overhead on large sites.

In such cases, use the `--skip-checks` (`-k`) option to bypass the filtering and process all files directly, which will be faster.
