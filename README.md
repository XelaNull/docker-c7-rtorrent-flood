**To Build:**

```
docker build -t c7/rtorrent-flood .
```

- You may need to add 1GB Swap to your build host via: dd if=/dev/zero of=/swapfile bs=1024 count=1048576 && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile

**To Run:**

```
docker run -dt -p8080:80 -p3000:3000 -p50000:50000 --name=rtorrent-flood c7/rtorrent-flood
```

**To Enter:**

```
docker exec -it rtorrent-flood bash
```
