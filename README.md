# phpbb_to_flarum
Script that will export the phpbb forum to a sql file that can be used to import the data into flaurm  see https://discuss.flarum.org/d/phpbb-migrate-script-updated-for-0-3-and-other-improvements for discussion
Thanks to viruxe https://github.com/viruxe for his updated script



##Usage Instructions 
For anyone who wants to use the script here are the steps I use

1. Create a fresh forum using the standard install instructions 
2. when you create the forum make sure the email is different to the one in phbb otherwise there will be some conflicts 
3. run the my script with the correct database parameters for you. (make sure that the script is executable and the directory writable) 
4. after the script has run you will see a file called flaurm.sql  import that file into your database and all your content will be migrated. 
