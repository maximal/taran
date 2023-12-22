# Taran

**Taran** is a tool for benchmarking web applications and servers.

“Taran” means “[battering ram](https://en.wikipedia.org/wiki/Battering_ram)” in Russian. 


## Build
Build into an executable file (`builds/taran`):
```shell
# Get the source code
git clone https://github.com/maximal/taran
cd taran
# Install PHP dependencies
composer install
# Build an executable
composer build
# Check
./builds/taran --help
```


## Usage

Run 10’000 GET requests against `http://localhost:8002` with concurrency level of 200 (requests in parallel):
```shell
./builds/taran --requests=10000 --concurrency=200 http://localhost:8002
```

Example output:
```plain
Running 10000 requests with concurrency of 200 to URL: http://localhost:8002 ...
[##############################################################################]
             URL:  http://localhost:8002
   Requests sent:  10000
     Concurrency:  200
        Timeouts:  2
    2xx statuses:  9998
Non-2xx statuses:  2
         Timings:  50.6 ms ± 183 ms  (avg ± std dev)
                   TTFB     Total
         minimum:  1.45 ms  2.32 ms  (fastest response)
   25 percentile:  2.96 ms  376 ms   (first quartile)
   50 percentile:  7.49 ms  516 ms   (median)
   75 percentile:  20.1 ms  1.01 s   (third quartile)
   90 percentile:  40.7 ms  1.12 s
   95 percentile:  57.3 ms  1.14 s
   99 percentile:  201 ms   1.20 s
         maximum:  1.28 s   2.00 s   (slowest response) 
     Total bytes:  275140000 B received, 0 B sent
      HTTP codes:
             200:  9998
         timeout:  2
Timing histogram:
 2.32 ms .. 6.31 ms  ################################                    125
 6.31 ms .. 10.3 ms  ####################################                141
 10.3 ms .. 14.3 ms  ##########################################          165
 14.3 ms .. 18.3 ms  ##################################################  197
 18.3 ms .. 22.3 ms  ########################################            156
 22.3 ms .. 26.3 ms  ####################################                143
 26.3 ms .. 30.3 ms  ################################                    126
 30.3 ms .. 34.2 ms  ########################                            95
 34.2 ms .. 38.2 ms  ###################                                 76
 38.2 ms .. 42.2 ms  ################                                    63
 42.2 ms .. 46.2 ms  #############                                       51
 46.2 ms .. 50.2 ms  #########                                           37
 50.2 ms .. 54.2 ms  ######                                              24
 54.2 ms .. 58.2 ms  ######                                              23
 58.2 ms .. 62.2 ms  ######                                              25
 62.2 ms .. 66.2 ms  #####                                               19
 66.2 ms .. 70.2 ms  ##                                                  9
 70.2 ms .. 74.1 ms  #                                                   4
 74.1 ms .. 78.1 ms  ##                                                  9
 78.1 ms .. 82.1 ms  #                                                   4
 82.1 ms .. 86.1 ms  #                                                   3
 86.1 ms .. 90.1 ms  #                                                   3
 90.1 ms .. 94.1 ms  ##                                                  7
 94.1 ms .. 98.1 ms  #                                                   3
 98.1 ms .. 102 ms   #                                                   2

... ... ...

10000 requests done in 52.3 s (191 average RPS).
```


### All Options
Execute `taran load --help` for the full help of `load` command:

```
Description:
  Load an URL with HTTP requests

Usage:
  load [options] [--] <URL>

Arguments:
  URL

Options:
  -r, --requests[=REQUESTS]        Number of requests to send [default: "100"]
  -c, --concurrency[=CONCURRENCY]  Number of parallel processes [default: "10"]
  -t, --timeout[=TIMEOUT]          Timeout of HTTP request in seconds [default: "2.0"]
  -b, --body[=BODY]                HTTP body to send in every request
      --histogram[=HISTOGRAM]      Timing histogram bars count (0 to disable) [default: "20"]
  -h, --help                       Display help for the given command. When no command is given display help for the list command
  -q, --quiet                      Do not output any message
  -V, --version                    Display this application version
      --ansi|--no-ansi             Force (or disable --no-ansi) ANSI output
  -n, --no-interaction             Do not ask any interactive question
      --env[=ENV]                  The environment the command should run under
  -v|vv|vvv, --verbose             Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```


## Coding Style
PSR-12T (PHP’s standard [PSR-12](https://www.php-fig.org/psr/psr-12/) with SmartTabs instead of spaces).


## Author
* Website: https://maximals.ru (Russian)
* Twitter: https://twitter.com/almaximal
* Telegram: https://t.me/maximal
* Sijeko Company: https://sijeko.ru (web, mobile, desktop applications development and graphic design)
* Personal GitHub: https://github.com/maximal
* Company’s GitHub: https://github.com/sijeko
