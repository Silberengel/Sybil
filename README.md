# Sybil Test Utility

![Sybil gazing into a crystal ball](https://i.nostr.build/Jo7qwDu7rgYkMIWJ.png)

## Description

This is a simple PHP program that takes an edited/pre-formatted Asciidoc document, splits it into the specific sections or chapters defined (based upon the first and second level headers, for further levels, please use the [Alexandria client](https://next-alexandria.gitcitadel.eu/about)), generates a [curated publication](https://next-alexandria.gitcitadel.eu/publication?d=gitcitadel-project-documentation-curated-publications-specification-7-by-stella-v-1) from that file, and writes it to the relays selected.

I've since begun to expand it, to keep up with the development of our web GUI.

## Directions

### Prerequisites

1. You will need to have php (including php-cli) and composer installed on the machine and configured.
2. Then run ```composer install``` to download the dependencies to a *vendor* folder and create the *composer.lock* file.
3. If you do not yet have the yaml PECL library installed, run 
```sudo apt-get install php-yaml```.
4. Then enter ```php --ini``` and look for the entry ```Loaded Configuration File``` that contains the filepath to the *php.ini* file.
5. Enter ```sudo nano <filepath>``` and add the line, at the bottom ```extension=yaml.so``` and restart the terminal.
6. If using VS Code or Codium, you will need to also go to *File > Preferences > Settings*. Search for *stubs*, click the *Edit settings in json* link, and then add *yaml* to the stubs list.
7. Save your Bech32 nsec in the environment variable with `export NOSTR_SECRET_KEY=nsec123`.
8. Open the folder *user* and edit the file *relays.yml* containing your list of relays. We recommend keeping wss://thecitadel.nostr1.com in your list and adding at least one other, that you have write access to. If you remove all relays, the Citadel relay will be used as default. ```a``` tags will always contain thecitadel relay as the relay hint.

### Curated Publications (30040)

1. Open the file in the *user* folder called *30040_settings_template.yml*, read the descriptions of the event tags at the top, and then copy out the `////<<YAML>>` section.
2. Paste the section *beneath* the document header (=) of your publication. The publication file should be located at in the top/root folder, be formatted in Asciidoc, and have an `.adoc` ending.
3. Edit the information within that yaml section and remove/add any optional tags.
4. If you like, you can do the same for each 1st-level header (==). That will give the 30041 events more-customized tags. Otherwise, they derive their tags from the 30040 index event.
5. On the command line, enter the program name, the command, and the name of your publication file.

```php Sybil.php publication MyShortBook.adoc```

6. All of the events will be added to the *eventsCreated.txt* file.
8. The 30040 eventID will be sent to stdout (usually the command line) in the form of an njump hyperlink. The link will not work, if you wrote to a local relay, but you can still see the eventID.

### Longform Articles (30023)
1. Open the file in the *user* folder called *30023_settings_template.yml*, read the descriptions of the event tags at the top, and then copy out the `////<<YAML>>` section.
2. Paste the section *above* the first header (#) in your article file. The article file should be located at in the top/root folder, be formatted in Markdown and have an `.md` ending.
3. Edit the information within that yaml section and remove/add any optional tags.
5. On the command line, enter the program name, the command, and the name of your article file.

```php Sybil.php longform MyArticle.md```

6. All of the events will be added to the *eventsCreated.txt* file.
8. The 30023 eventID will be sent to stdout (usually the command line) in the form of an njump hyperlink. The link will not work, if you wrote to a local relay, but you can still see the eventID.

### Wiki Pages (30818)
1. Open the file in the *user* folder called *30818_settings_template.yml*, read the descriptions of the event tags at the top, and then copy out the `////<<YAML>>` section.
2. Paste the section *above* the first header (=) in your wiki file. The wiki file should be located at in the top/root folder, be formatted in Asciidoc, and have an `.adoc` ending.
3. Edit the information within that yaml section and remove/add any optional tags.
5. On the command line, enter the program name, the command, and the name of your wiki file.

```php Sybil.php wiki WikiPage.adoc```

6. All of the events will be added to the *eventsCreated.txt* file.
8. The 30818 eventID will be sent to stdout (usually the command line) in the form of an njump hyperlink. The link will not work, if you wrote to a local relay, but you can still see the eventID.

### Utilities

The following utilities are available:

#### Fetch

Takes the argument *hex eventID* and looks for it in the standard relay set.

```php Sybil.php fetch 108657...```

#### Delete

Takes the argument *hex eventID* and sends a deletion request to the standard relay set.

```php Sybil.php delete 108657...```

#### Broadcast

Takes the argument *hex eventID*, fetches the event, and broadcasts it to the standard relay set.

```php Sybil.php broadcast 108657...```


## Automated Tests

To check that everything is installed correctly, you can run 

```
./vendor/bin/phpunit src/
```

to see the unit and integration tests, if you have PHPUnit installed.

## Example

If you go to [next-alexandria](https://next-alexandria.gitcitadel.eu), you can then see the publications which you have produced, so long as you have published them to [wss://thecitadel.nostr1.com](https://thecitadel.nostr1.com) or one of your npub's outbox relays.

An example of a typical document, produced with this tool, is the test file `AesopsFables_testfile_a.adoc` which looks like this:

![source](https://i.nostr.build/qE3NVGZCTQ59TVEl.png)

And produces a book, like this:

![landing page](https://i.nostr.build/gxf1A3WsYyJ64vVu.png)

![json view](https://i.nostr.build/uS56H0WYGt5qcUiR.png)

![detailed view](https://i.nostr.build/zHobLiKYjfvsIwTI.png)

![reading view](https://i.nostr.build/DC7ROcViizNRzbzV.png)

## Contact information

If you have any questions, comments, or zaps, then please feel free to contact me on Nostr. My npub is [npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z](https://njump.me/npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z).
Or use the issues on [the GitCitadel homepage](https://gitcitadel.com/r/naddr1qvzqqqrhnypzplfq3m5v3u5r0q9f255fdeyz8nyac6lagssx8zy4wugxjs8ajf7pqythwumn8ghj7un9d3shjtnwdaehgu3wvfskuep0qqz4x7tzd9kqftxaxq)