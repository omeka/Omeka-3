# Omeka 3 (Multisite)

## Installation

1. Clone this repository in your Apache web directory:
   `$ git clone git@github.com:omeka/Omeka3.git`
2. Install [Composer](http://getcomposer.org/): 
   `$ curl -sS https://getcomposer.org/installer | php`
2. Change into the Omeka3 directory:
   `$ cd Omeka3`
3. Install the [Doctrine](http://www.doctrine-project.org/) environment: 
   `$ ./composer.phar install`
4. Copy and rename the application config file: 
   `$cp config/application.config.php.dist config/application.config.php`
5. Open config/application.config.php and add your MySQL username, password, and 
   database name.
6. Create the Omeka database: `$ php vendor/bin/doctrine orm:schema-tool:create`
7. In your web browser, navigate to the Omeka directory.

You can find Omeka-specific code under module/Omeka. You may include 
bootstrap.php in your own script and use the `$em` entity manager to work with 
Omeka's ORM. See Doctrine's 
[documentation](http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/index.html).

## Libraries Used

Omeka uses the following libraries

* [Zend Framework 2](http://framework.zend.com/)
* [Doctrine](http://www.doctrine-project.org/)
* [Composer](http://getcomposer.org/)
* [jQuery](http://jquery.com/)
* [Symfony Console](http://symfony.com/doc/current/components/console/introduction.html)

## Coding Standards

Omeka development adheres to the [Zend Framework 2 Coding Standards](http://framework.zend.com/wiki/display/ZFDEV2/Coding+Standards)
