# allowance-ledger
This is a very simple script that I use for keeping track of day-to-day transactions.  I keep a seperate spreadsheet for keeping track of my main budget where I effectively pay myself an allowance each paycheck and keep track of that money here.  I'm sure this wouldn't work for everyone, but it works really well for me :)

# Setup
This repo relies on adamtomecek's Template manager, the most bare-bones but solid templating engine I could find for this project. Bootstrap 5 and Bootstrap Icons are loaded from CDN so no local installation is needed. Here's how to set it up:

1. Clone this repo and go into that directory
2. Clone the template manager code
3. Import ledger schema
4. Create .env (described below)

```
git clone git@github.com:thejart/Template.git templateManager
mysql -uUSER -p DATABASE < mysql-schemal.sql
```

.env contains the username, password, database, and table to your local mysql server, newline delimited like:
```
username
password
database
table
```

I suggest chown'ing the file root:www-data (or whatever user apache runs as on your system) and chmod'ing it 640 for some semblance of security.
```
sudo chown root:www-data .env
sudo chmod 640 .env
```

Once all of that is in place, you'll have a blank slate for keeping track of your transactions. (Hint: start by adding money)
