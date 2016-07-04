# backbone.js -- Redirect/Migrate tool

This app is a backbone.js skeleton used to navigate across the views and get the data from the server.

*Rough data flow:*
Loading screen -> Redirect/Migrate screen -> Success screen

Assuming that each post has 2 states - Migrate or Redirect, we send an AJAX request to the server to get the post status and then render a template, using backbone.js

For the testing purposes, a server responds with a different data each page load, so just refresh the page a couple of times or hit "Re-Check button".

When you hit "Confirm", a success view will be displayed.

# Installation

Clone this repo and run this command:

```bash
npm install
```

Once complete, run the app:

```bash
npm app
```
