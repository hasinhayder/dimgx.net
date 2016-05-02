This is the sourcecode of dimgx.net

The main trick is the nginx configuration file, and the way missing files are bypased to  fallback index.php so that it can process the request and serve the file. 

Dimgx.net effectively retrieves the size of the requested remote files by only downloading a chunk from their headers instead of the whole file, which makes the whole process lot faster and efficient. Dimgx also caches the previous requests so that it doesn't have to download remote files of same sizes again.
