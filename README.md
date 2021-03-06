# Testsite Builder

The Testsite Builder provides Drush commands for the creation of test sites based on the report generated by [Sampler module](https://github.com/thunder/sampler).

## Prerequisites
Your project should be set up to use composer for installing the required modules. Projects that have a different setup are
not supported.

This module provides Drush commands only. If you do not already use Drush, install it before continuing.

## Installation

In your project-root do:

    composer require thunder/testsite_builder

Then enable the testsite_builder module, either with command line or in the admin UI.

    drush en testsite_builder

Flush the caches, and you are ready to go.

### Adjust database
To use the content-create functionality of the testsite_builder, adjust the MySQL database settings.

#### Grant files
Login as root and execute

    GRANT FILE on *.* to 'drupaluser'@'localhost'
    GRANT SUPER on *.* to 'drupaluser'@'localhost'

#### Adjust config
Open your my.cnf and add
```
[mysqld]
   secure-file-priv=""
```

Restart the MySQL Server.

## Usage

#### Create configuration

To create site configuration from Sampler report, you can execute the following command:
`drupal testsite-builder:create-config <sampler report JSON file>`

That command removes all existing content and configuration for the site and generates a new configuration from the provided Sampler report file.

#### Create configuration and content

To create site configuration from Sampler report and content for it, you can execute the following command:
`drupal testsite-builder:create-config <sampler report JSON file> --create-content`

That command removes all existing content and configuration for the site, generate new configuration and content from the provided Sampler report file.

The Testsite Builder executes the content creation task in the following steps:
1. it creates content and stores them in temporal CSV files ready for database import
2. it imports created CSV files into database
3. it deletes temporal CSV files after database import

If you want to investigate CSV files and intermediary configuration used for content creation, you can add an option to the previous command to keep created files. Like this:
`drupal testsite-builder:create-config <sampler report JSON file> --create-content --keep-content-files`

In this case, the Testsite Builder skips step 3, and the command outputs the path to temp directory with generated CSV files.
