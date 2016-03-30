# phpbb_to_flarum
Migration Script from PHPBB to Flarum 
Discussion https://discuss.flarum.org/d/1117-phpbb-migrate-script-updated-for-0-3-and-other-improvements

The Script performs a DB -> DB migration it will coppy all usernames and emails and start date but *Will NOT COPY PASSWORDS* instead it will create a random passowrd which is a md5 hash of the current time that is then shad1. This means after the migration all users will need to reset their passwords



##Usage Instructions 
For anyone who wants to use the script here are the steps I use

1. Create a fresh forum using the standard install instructions 
2. when you create the forum make sure the email is different to the one in phbb otherwise there will be some conflicts 
3. run the my script with the correct database parameters for you. (make sure that the script is executable and the directory writable) 



###Contributions welcome 
Thanks to all who have contributed :)
