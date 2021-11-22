svntime
=======

A simple script to calculate the amount of time spent by committers on a Subversion repository (or, [with GitHub's SVN bridge](https://help.github.com/articles/support-for-subversion-clients/)).

Available on Google Code (SVN): https://code.google.com/p/svntime/
Or GitHub (Git): https://github.com/soundasleep/svntime

**UPDATE:** This script has been superceded by [gittime](https://github.com/soundasleep/gittime) which has tests and support for more data sources.

## Composer support

`svntime` can now be installed with [Composer](https://getcomposer.org/) through [Packagist](https://packagist.org/packages/soundasleep/svntime):

```json
{
  "require": {
    "soundasleep/svntime": "*"
  }
}
```

## Connecting to GitHub

```shell
rem 60 min = 3600 sec
rem 120 min = 7200 sec
php -f vendor/soundasleep/svntime/svntime.php -- --between 7200 --before 7200 --after 3600 https://github.com/user/repo1 https://github.com/user/repo2
```
