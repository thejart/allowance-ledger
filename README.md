# allowance-ledger
This is a very simple (and old) script that I use for keeping track of day-to-day transactions.  I keep a seperate spreadsheet for keeping track of my main budget where I effectively pay myself an allowance each paycheck and keep track of that money here.  I'm sure this wouldn't work for everyone, but it works really well for me :)

# Setup
You'll need to create file called env.setup which contains the username, password, database, and table to your local mysql server, newline delimited like:
```
username
password
database
table
```
I suggest chown'ing the file root:www-data (or whatever user apache runs as on your system) and chmod'ing it 640 for some semblance of security.

Secondly, you'll need to create the finance database and ledger table from mysql.schema provided.

Once those are in place, you'll have a blank slate for keeping track of your transactions.

# Caveats
I originally wrote this code about 10 years ago; it's ugly and full of SQL-injection vulnerabilities.  I'm putting this (and eventually some other stuff) on github as-is.  I plan on cleaning this up and adding a few features, but the damn thing's simple and has served my purpose for a while.
