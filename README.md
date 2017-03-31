# Codecourse Downloader
Download codecourse.com videos.
![Screenshot](screenshot.png)
## Description
Inspired from iamfreee/laracasts-downloader. Download or update your local catalogue with codecourse.com series.

If you dont want to save your username and password in configuration file just remove username and password settings from configuration file. It will ask your username and password after you run downloader.

#### An account with an active subscription is necessary!

## Requirements
- PHP >= 5.4
- php-cURL
- php-xml
- Composer

## Installation
- Clone this repo to a folder in your machine
- Change your info in .env.example and rename it to .env
- Open up .env file change the value of CCUSERNAME to your Code Course Email and CCPASSWORD to your Code Course Password.
- `composer install`
- `php codecourse download` and you are done!

#### Downloading series.

if you want to download specific series you can pass series slug as an argument.

For example if you want to download https://www.codecourse.com/lessons/learn-es6
```
php codecourse download learn-es6
```
