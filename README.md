# Docker CentOS7 LAMP + rTorrent + Flood + Pyrocore

This project was created after the realization that the torrent clients built into Torrentflux-b4rt were so old and out-of-date that most modern trackers don't allow any of them. After research, I discovered most trackers do support all version of rTorrent, which is an excellent back-end for managing thousands of torrents at-a-time. I then found Flood, which is a modern UI front-end for rTorrent and is a very clean interface.

## **Flood:**

<https://github.com/jfurrow/flood>

While Flood is beautiful, it does seem to be missing some key-features that still need to be met for a quality seedbox. Namely, automated control over when a torrent stops seeding, based on either time or seed ratio met. To meet these needs, I found Pyrocore, or more specifically rtcontrol which is part of the Pyrocore suite of utilities.

## **Pyrocore & rtcontrol**

- **Pyrocore:** <https://github.com/pyroscope/pyrocore>
- **Pyrocore Documentation:** <https://pyrocore.readthedocs.io/en/latest/usage.html>
- **Examples of rtcontrol Usage:** <https://pyrocore.readthedocs.io/en/latest/usage.html#fixing-items-with-an-empty-base-path>

rtcontrol is an extremely powerful back-end addition to rTorrent that allows a wide-array of options. This project uses it simply to control when a torrent should stop seeding based on either 3 days passing or the seed ratio reaching 100%. These values are variables you can adjust at the top of this Dockerfile. If you wanted, you could even add rtcontrol queue support. Although, I've not found a need to use this yet.

## **rTorrent configuration**

This project's rTorrent is set up so that when you initially add a .torrent to the Flood web interface, it will start the download into /var/www/html/incomplete. This path is accessible via your web browser at: <http://YOURIP:8080/incomplete>. When the torrent download is completed, the entire torrent download is moved to /var/www/html/complete, where it will continue seeding until the time or ratio requirements are met. This path is also accessible via your web browser at: <http://YOURIP:8080/complete>. At the time your torrent is moved from incomplete to complete directory, rTorrent will call the "unrarall" command to automatically extract any rar files within this torrent.

If you wish to remove this extraction behavior, I've provided an alternate configuration line in the rtorrent.rc file in this project. Just comment out the line above it, and then uncomment the alternate configuration line and then rTorrent will not extract the rar files but simply move the torrent data as-is from incomplete to complete. Please be aware that scan.php will NOT expose the URLs of rar files, so if you disable automatic extraction of rar files, and you are using scan.php, you will need to modify the extension list that scan.php exposes to include your rar files.

## To download your torrent data

There are three ways that you can download your torrent data:

- You can right-click on each torrent in the Flood web UI and choose the download option. Great, if you only have a few torrents.
- You can visit the completed URL at port 8080 (<http://YOURIP:8080/completed>), and click each file one-by-one to download. Tedious, especially for torrents with many files.
- You can use the built-in scan.php URL (<http://YOURIP:8080/scan.php>) and install the grab.php project elsewhere and use that to download all completed torrent data as one command.

## **scan.php**

There is a PHP script at <http://YOURIP:8080/scan.php> that will provide you a one-per-line listing of all movie files detected in your /var/www/html/complete directory, and provides a convenient full URL to download each file. I provide this, to provide an easy method of exporting a full list of all completed files that I would care to download as a batch. Scan will first only provide download URLs to files that meet the following criteria:

- File extension of: "img","mp4","avi","mkv","m2ts","wmv","iso","divx","mpg","m4v"
- Filename must not contain the word "sample", in any capitalization

In the future, I may convert the output of this script to JSON to better match modern standards.

## **grab.php**

I needed a way to download all completed torrent data easily with one-command. Clicking around in a web-interface to download each torrent individually just will not meet my need. So I created a separate script that can be run from any machine capable of running PHP, that will contact the rTorrent+Flood server via HTTP URL and will automatically download all completed torrent data to this separate server running grab.php. This way, I can set up an automated job to run on a separate server and execute this grab.php and download any completed torrent data from the rTorrent+Flood server.

The grab.php script will accept the argument "YOURIP:8080" and will contact the scan.php from the rtorrent+flood instance via HTTP URL and use "wget -c" to download all of your torrent data. The -c option on wget causes it to act very similar to "rsync -r --partial". If your grab.php stops downloading a file, and then you restart grab.php later, it will pick-up where it left-off and will skip past any files it detects are already present on the local system. It will only "grab" or download any files that scan.php exposes to it. So no .nfo, .txt, or any other file extension that scan.php is not configured to expose.

grab.php also has file sanitization built in to it, to ensure the names that are saved to your system are clean and standardized. This sanitization will take a name that may look like:

Media.Name.2018.1080p.BRRip.XviD.AC3

and convert it to look like:

Media Name 2018 1080p

The sanitization is performed on both directories as well as files. Please see the grab/README.md for further information.

I've provided a Dockerfile for grab, but frankly, I've found that running grab.php outside of Docker is roughly twice as fast.

--------------------------------------------------------------------------------

This package is reliant upon my docker-c7 project. As such, you will need to build the docker-c7 project before building this one. I've created a simple one-liner script to take care of building and running both projects with their default options. This results in an rTorrent+Flood instance that is ready for you to log in and use.

## To build the docker-c7 & docker-c7-rtorrent-flood projects

```
curl -o latest -L https://raw.githubusercontent.com/XelaNull/docker-c7-rtorrent-flood/master/latest && sh latest
```

When the script finishes, your Docker image will be started and you should immediately be able to access it via port 8080 at your IP address. <http://YOURIP:8080> The login and password you provide on your first login attempt will be created as your administrative account and can be reused afterwards. For the rTorrent configuration use: IP: 127.0.0.1 Port: 5000

The only ports this project expose are:

- TCP port 3000 : Used for the Flood Web UI. This is your primary port for managing your torrents.
- TCP port 8080 : Used to access and download

--------------------------------------------------------------------------------

## **To Build:**

```
docker build -t c7/rtorrent-flood .
```

- You may need to add 1GB Swap to your build host via: dd if=/dev/zero of=/swapfile bs=1024 count=1048576 && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile

## **To Run:**

```
docker run -dt -p8080:80 -p3000:3000 -p50000:50000 --name=rtorrent-flood c7/rtorrent-flood
```

## **To Enter:**

```
docker exec -it rtorrent-flood bash
```
