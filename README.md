# sbackup-dropbox

SBackup adapter to work with backups on Dropbox

## Install

To use the library you can just import to your project using composer:

```console
composer require genilto/sbackup-dropbox
```

## Testing

You can test the lib using the content of html folder. There is an example of how to use this library.

It's also possible test using docker:

```console
docker-compose up -d
```

It will start a docker container, running an apache and php on http://localhost:86.

1. The very first thing you need to do is to authenticate SBMailer with dropbox: http://localhost:86/auth.php

2. Then you can upload files using the upload form in http://localhost:86. Selecting a file and clicking on Upload button, the file will be uploaded to the root of your Dropbox.
