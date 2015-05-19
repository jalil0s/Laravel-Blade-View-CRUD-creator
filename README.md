# Laravel-Blade-View-CRUD-creator
A very hacky way of generating some Blade templates for an existing table

## IMPORTANT: this isn't really ready for prime time - I put it here at the request of someone on irc.
### Take a bit more time to understand what this does before diving in.

### prereqs: 
You must already have your tables created in mysql, you must already have your models created (I used php artisan make:model for mine)

### How this came to be.
 I had an existing DB from another project and I wanted to try out laravel.
 to use this DB, I needed 4 things per table:
- model 
- controller 
- blade views
- route entry in routes.php

 Creating the models is pretty easy using php artisan make:model so thats what I used.
 
 When I got to the views, I was a little overwhelmed. My DB had 14 tables, and probably 100 fields.
 It was going to be a LOT of work to just get some basic CRUD setup before I could even get into the fun stuff!
 
 So I created the class file thats in this project.
 
# Basic instructions for use
- note - this is **not** a laravel package! (yet)
- note - this was developed on osx, haven't tried with windows paths.

1. download everything here - ie clone the repository to your system
2. open the file Class_Query2BladeTemplate.php
3. at around line 5, put in your DB connect string, (works only with mysql) port 8889 for mamp users, port 3306 for everyone else.
4. at around line 10 set the value of pathToProjectFolder this should be the full path to your laravel install
5. you probably should comment out the line $this->ResetDirectories() (about line 76 in the function SaveAll())

ok with that out of the way, 
To run this, you'll open a terminal, navigate to the directory where this is and type
```php
php Class_Query2BladeTemplate.php
```

If all goes well, you'll have:
  - app\http\controlers\admin (contatins controllers created by this script)
  - resources\views\admin (contains the blade templates created by this script)
  
At the top of every controller that was generated, is a sample block of code you can add to your routes.php file  

Note that I create a subfolder called 'admin' the idea behind this was two fold: 

1. I didn't want to accidentally overwrite any pre-existing views/controllers.
2. it made sense that you'd use this for a quick and dirty 'admin' interface.

You can easily change 'admin' to anything else by setting the $subFolder to something else.

Here are a few screenshots:
![Image of Index view]
(http://content.screencast.com/users/basementjack/folders/Snagit/media/3ff0bd57-abe6-4320-9cc0-e1b5e6df5698/2015-05-18_20-29-52.png)

On this screenshot, notice the hint that reminds you that you need to set the fillable property of your model.
it's worth mentioning that this hint is a *real* example that has your actual field names.
![Image of Edit view]
(http://content.screencast.com/users/basementjack/folders/Snagit/media/f9621a0a-34be-459a-93ce-d974b5249963/2015-05-18_19-24-46.png)

This is a screenshot of the controllers generated (left side)
On the right side, note that in the comments of the controllers is a block of code you can copy into your routes file if you'd like.
![Image of Controller code]
(http://content.screencast.com/users/basementjack/folders/Snagit/media/0d5f0782-9514-445e-b115-ba77ce401fed/2015-05-18_20-32-07.png)

This is a screenshot of the views generated (left side)
On the right side, note the blade template, and note that we generate, but comment out by default, system fields like ID, created_at and updated_at.

![Image of View code]
(http://content.screencast.com/users/basementjack/folders/Snagit/media/345898d4-6112-434c-9376-0897e33113c1/2015-05-18_20-35-08.png)
