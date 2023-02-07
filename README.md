## PHP Monorepo Generator

### Idea

Generate a monorepo structure and/or package for various frameworks. (currently supporting laravel)

### Usage

Inside your empty project directory run the following command
```
compsoer require --dev drmovi/php-monorepo-generator
```

Then run the following command

```
vendor/bin/dmg monorepo:package:generate <<package-name>>
```

The structure generated is like the following:

```

|- app -> contians the framework app e.g. laravel
|
|- devconf -> contains the configuration files for the development environment e.g. phpstan
|
|- k8s -> contains the kubernetes configuration files if mode selected is microservice
|
|- packages -> contains the main packages
|
|- shared -> contains the shared packages that can be used by packages
|
|- skaffold.yaml -> main skaffold file
|
|- makefile -> main makefile contains shortcuts to all useful commands for development and testing
|
|- Dockerfile -> main dockerfile to build application
|
|- phpunit.xml -> main phpunit configuration file

```

If you chose to generate a microservice, note that the following rules will be applied phpstan:

* Files inside each package can't be used outside its own package
* Files inside shared can be used by any package
* Files inside app can be used by any package
* Files inside shared and packages can't be used by app

This is to ensure microservice bounded context is applied.


### Notes

* There is a shared package created called 'api', the idea is to put all the api related code of any package inside this shared package, so that it can be used by any package that needs to expose or consume an api of other packages.
