# Alexandria Upload Utility

## Description

This is a simple PHP program that takes an edited/pre-formatted Asciidoc document, splits it into the specific sections or chapters defined (based upon the first and second level headers, for further levels, please use the [Alexandria client](https://gitcitadel.com/r/naddr1qvzqqqrhnypzplfq3m5v3u5r0q9f255fdeyz8nyac6lagssx8zy4wugxjs8ajf7pqy2hwumn8ghj7un9d3shjtnyv9kh2uewd9hj7qq2g9kx27rpdej8y6tpq7z4jt)), generates a [curated publication](https://github.com/nostr-protocol/nips/pull/1600) from that file, and writes it to the relays selected.

## Prerequisites

1. You will need to have php (including php-cli) and composer installed on the machine and configured.
2. Then run ```composer install``` to download the dependencies to a *vendor* folder and create the *composer.lock* file.
3. If you do not yet have the yaml PECL library installed, run ```sudo apt-get install php-yaml```.

## Directions

1. Save your Bech32 nsec in the environment variable with `export NOSTR_SECRET_KEY=nsec123`.
2. Open the folder *user* and edit the file *relays.yml* containing your list of relays. We recommend keeping wss://thecitadel.nostr1.com in your list and adding at least one other, that you have write access to. If you remove all relays, the Citadel relay will be used as default. ```a``` tags will always contain thecitadel relay as relay hint.
3. Copy the file in the *user* folder called *settings_template.yml* and paste it into the same folder, giving it a name similar to your publication title. Edit the information within and remove/add any optional tags.
4. Return to the main/upper folder, create an Asciidoc file entitled something like *MyShortBookTitle.adoc* and have it formatted with precisely two levels of headers.

```
= title you want as the full book title (mind the space after the equal sign)

== topic1

text that you want displayed as content

== topic2

more text
```

5. On the command line, enter the program name and the name of your settings file.

```php createBook.php user/MyShortBookSettings.yml```

6. All of the event metadata will be added to the *eventsCreated.txt* file.
8. The 30040 eventID will be sent to stdout (usually the command line) in the form of an njump hyperlink. The link will not work, if you wrote to a local relay, but you can still see the eventID.

## Integration Test

To check that everything is installed correctly, you can run 

```
./vendor/bin/phpunit src/IntegrationTest.php
```

to see the integration test, if you have PHPUnit installed.