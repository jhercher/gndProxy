# GND Proxy 

a tool to do parallel request to different webservices, fetching data from wikidata, wikipedia, german national library, and culture graph.
Results are merged into a single JSON object. Use this as backend for [wikibox](https://github.com/jhercher/wikibox) (a script to create beautiful tooltips with data from this service).

### Dependencies
This script makes use of:
 * [**easyrdf library** by  Nicholas Humfrey](http://www.easyrdf.org/), needs to be downloaded seperately.
 * [**curl multirequest** from Stoyan Stefanov](http://www.phpied.com/simultaneuos-http-requests-in-php-with-curl/)
 
### Installation:

`git clone` this repo

download the [easyrdf library](http://www.easyrdf.org/) and move it into your repo.

This script uses curl so configure the path to your servers ssl-certificate in `multirequest.inc`.

Example: `http://localhost/gnd.php?query=118587943&services=cult,dnb,wiki`

### Meta 
This script was developed with ♥ as showcase for [Coding da Vinci](http://codingdavinci.de/) cultural hackathon.

Pull requests welcome!
