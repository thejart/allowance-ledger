# allowance-ledger
This is a very simple script that I use for keeping track of day-to-day transactions.  I keep a seperate spreadsheet for keeping track of my main budget where I effectively pay myself an allowance each paycheck and keep track of that money here.  I'm sure this wouldn't work for everyone, but it works really well for me :)

# Setup
This repo relies on a couple of 3rd party libraries, Twitter's Bootstrap and adamtomecek's Template manager.  The former is obviously well known, while the latter is the most bare-bones, but solid templating engine that I could find (at least for the purposes of this small project).  The locations of these two libaries are currently hard-coded to bootstrap/ and templateManager/ respectively.  I may change this to be more accomodating if needed, but for now that's how it is.  Here's how you can set it this up for yourself:

1. Clone this repo and go into that directory
2. Clone the template manager code
3. Download, unzip bootstrap, and move it to the bootstrap directorty
4. Import ledger schema
5. Create .env (described below)

```
git clone git@github.com:thejart/Template.git templateManager
wget https://github.com/twbs/bootstrap/releases/download/v3.3.6/bootstrap-3.3.6-dist.zip
unzip bootstrap-3.3.6-dist.zip
mv bootstrap-3.3.6-dist bootstrap
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
