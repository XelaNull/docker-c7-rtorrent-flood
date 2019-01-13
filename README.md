**To Build:**

```
docker build -t c7/rtorrent-flood .
```

**To Run:**

```
docker run -dt -p8080:80 --name=rtorrent-flood c7/rtorrent-flood
```

**To Enter:**

```
docker exec -it rtorrent-flood bash
```
