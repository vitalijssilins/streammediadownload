Download stream media from GrooveShark.com, MixCloud.com, Soundcloud.com - Stream Media Download
=========

Stream Media Download is a PHP script, which is capable of getting stream links from different streaming services.
You can fork this project and use AbstractAdapter.php class to add additional streaming services.
You have three well documented examples to get started.

Requirements
--------------
Be sure to install these libraries on the server, before using Stream Media Download:
```
curl
ssl
swftools (only for grooveshark - see below)
```


Installation
--------------
Stream Media Download uses composer to maintain dependencies. Installation procedure:

```sh
git clone [git-repo-url] streammediadownload
cd streammediadownload
php composer.phar install
```
After installation take a look at **/examples** folder.


SFW Decompiler
-----------
*GrooveShark.com* adapter uses *swfdump* command to decompile swf and get a token key for communication. If you plan to use GrooveShark then please install swftools on the server.
Use this command depending on your linux environment:

```
sudo yum install swftools
```
or
```
sudo apt-get install swftools
```
Version
----

1.0

License
----

GPL v3