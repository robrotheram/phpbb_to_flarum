# phpbb_to_flarum
Migration Script from PHPBB to Flarum 
Discussion https://discuss.flarum.org/d/1117-phpbb-migrate-script-updated-for-0-3-and-other-improvements

The Script performs a DB -> DB migration it will copy all usernames and emails and start date but *Will NOT COPY PASSWORDS* instead it will create a random passowrd which is a md5 hash of the current time that is then shad1. This means after the migration all users will need to reset their passwords



## Usage Instructions 
For anyone who wants to use the script here are the steps I use

1. Create a fresh forum using the standard install instructions.
2. When you create the forum make sure the email is different to the one in phbb otherwise there will be some conflicts.
3. Run the my script with the correct database parameters for you. (make sure that the script is executable and the directory writable).

### Usage using phpMyAdmin tested by [demmm](https://github.com/demmm)
1. Use phpMyAdmin on the old server with phpBB to export, download that database.
2. Create a new forum with a clean database on the new server, for Flarum.
3. Create another database on that new server, this one to import the old phpBB database into, and run the script.



## Contributions Welcome 
Thanks to [all who have contributed](https://github.com/realodix/phpbb_to_flarum/graphs/contributors) :)
