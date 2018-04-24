effectdigital/gdpr-sanitise
===========================



[![Build Status](https://travis-ci.org/effectdigital/gdpr-sanitise.svg?branch=master)](https://travis-ci.org/effectdigital/gdpr-sanitise)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using
 Command to delete common personal data that may be regulated by the GDPR including non-admin WP users, Gravity Forms entries and WooCommerce orders on staging and local development environment.
 
 Will refuse to run if using .env and environment is set to production.
  
 Only one command, which asks for confirmation and deletes everything. 
    
    wp gdpr-sanitize


## Installing

Installing this package requires WP-CLI v1.1.0 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with:

    wp package install git@bitbucket.org:effectdigital/effectdigital-gdpr-sanitize.git
