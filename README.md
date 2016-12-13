# esp for RACHEL

A system for providing remote service and support to RACHEL devices
in the field.

## client/

- **checker.php** - Runs in the background on a RACHEL device, should be
  started at boot. It queries our central server and when requested sets up
  ssh tunnels so we can work on the device, wherever it is.

- **esp.sshkey** - A key for connecting to an unprivileged account on the
  central server. The version checked in here is a dummy key that won't work.

## server/

- **server.php** - Resides on our central server and takes incoming queries
  from RACHEL devices (via HTTP). It updates the central DB, and issues
  commands to remote devices to connect or disconnect.

- **view.php** - Allows viewing the currently available devices and their
  status. Polls the DB through AJAX. This is the main point of entry for an
  admin user.

- **stat.php** - AJAX support for view.php

- **lib.php** - Some shared code for the server scripts above.

# Server Setup

```
sudo bash
adduser esp
    ...
su esp
cd
ssh-keygen
cat .ssh/id_rsa.pub >> .ssh/authorized_keys
exit
chsh esp
    /usr/sbin/nologin
```

You need to copy `/home/esp/.ssh/id_rsa` to `client/esp.sshkey` on any device
that is going to connect. 

You need to add the following to `/etc/sshd_config`:

```
    GatewayPorts yes
    ClientAliveInterval 20
    ClientAliveCountMax 3
```
