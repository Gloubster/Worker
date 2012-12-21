# Gloubster Worker

[![Build Status](https://secure.travis-ci.org/Gloubster/Worker.png?branch=master)](http://travis-ci.org/Gloubster/Worker)

Gloubster Worker runs against a Gloubster-Server and runs job.

## Installation

Use composer to install the worker. As there is no tagged version of Gloubster
Worker, use the `--stability` option :

```
[ user@host : /usr/local/src ] $ composer create-project --stability=dev gloubster/worker gloubster-worker
```

## Configuration

You have edit config/config.json to use the worker.
There are two interesting properties in the configuration :

 - *server* : is the RabbitMQ server configuration. You have to provide the access
   to the rabbitMQ server that distribute the jobs. In a soon future, this
   configuration will be distributed by GloubsterServer.

 - *workers* : workers defines wich type of workers are available for running.

{
    "server": {
        "host": "localhost",
        "port": 5672,
        "user": "guest",
        "password": "guest",
        "vhost": "/"
    },
    "workers": {
        "image": {
            "queue-name": "Gloubster\\RabbitMQ\\Configuration::IMAGE_PROCESSING"
        },
        "video": {
            "queue-name": "Gloubster\\RabbitMQ\\Configuration::VIDEO_PROCESSING"
        }
    }
}

## Execution

To run a worker defined in the workers section, just use the command :

```
[ user@host : /usr/local/src/gloubster-worker ] bin/worker run image
```

## Update

No easy update for the moment

## License

Released under the MIT license.