# WEMO Always On

This repo contains a quick script that looks for any belkin wemo insight devices on the network that match a set of regexes and then ensures that they are always powered on.

This is mainly to work around the fact that the wemo's default to "off" after power has been restored to them following a power failure/brownout.

I use the wemo for power-graphing rather than remote-power-on functionality, and all my networking kit runs through the wemo, so it is non-useful if this does not come back on after a power problem.

Fortunately for me, the networking load is supported by a UPS, so by cronning this script to run every few minutes (more frequently than the UPS minimum run time!) we can ensure that the wemos are always on where possible.
