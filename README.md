What is phpsass4jelix ?
==============================

This project is a plugin for [Jelix](http://jelix.org) PHP framework. It allows you to use easily [Sass](http://sass-lang.com/) dynamic stylesheet language in Jelix (using [phpsass](http://phpsass.com/) compiler).

This is an htmlresponse plugin.



Installation
============

Under Jelix default configuration, create an "htmlresponse" directory in your project's "plugins" directory.
Checkout this repository in that directory with :

    git checkout --recursive git@github.com:brice-t/phpsass4jelix.git
    mv phpsass4jelix/phpsass .
    rm -r phpsass4jelix


Note that you should have your app plugin directory in your modulesPath (defaultconfig.ini.php or entry point's config.ini.php) to get it working.
The value should be at least :

    modulesPath="app:modules/"



Usage
=====

When including a CSS file (e.g. with addCSSLink()) you should set 'sass'=>true as a param.

Another way of having a file compiled with Sass is including as file with .scss or .sass extension. You can set expected extensions in the comma-separated value of _phpsass\_extensions_ under the _jResponseHtml_ section.

E.g. in your response :

`$this->addCSSLink($gJConfig->urlengine['basePath'].'themes/'.$gJConfig->theme.'/Css/style.sass');`

or

`$this->addCSSLink($gJConfig->urlengine['basePath'].'themes/'.$gJConfig->theme.'/Css/style.sassFile', array( 'sass' => true ));`


Your config file must activate phpsass plugin :

    [jResponseHtml]
    plugins=phpsass

N.B. : the directories containing Sass files should be writable by your web server ! Indeed, compiled files will be written in that very same directory so that relative urls go on working ...




Config
======

You can configure phpsass's behviour regarding compilation:

    [jResponseHtml]
    ;...
    ; always|onchange|once
    phpsass_compile=always

If phpsass\_compile's value is not valid or empty, its default value is onchange.

* always : compile Sass file on all requests
* onchange : compile Sass file only if it has changed
* once : compile Sass file once and never compile it again (until compiled file is removed)

