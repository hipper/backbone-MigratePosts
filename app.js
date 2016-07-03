'use strict';

var express = require('express');
var app = express();

/**
 * Custom delay for backbone.js to display a loading message
 */
var delay = function stateChange(req, res, next) {
    setTimeout(function(){
        next();
    }, 1000);
};

/**
 * Serve static files
 */
app.use(express.static(__dirname + '/public'));

/**
 * Route for Backbone.js
 */
app.get('/route', delay, function(req, res) {
    var bodyObject = [{
            rawscore: 0.75,
            title: "Sample duplicate post",
            path: "#"
        },
        {
            rawscore: 0.1231,
            title: "Lorem ipsum dolor sit amet",
            path: "#"
        },
        {
            rawscore: 0.8231,
            title: "Consectetur adipiscing elit",
            path: "#"
        },
        {
            rawscore: 0.5231,
            title: "Duis aute irure dolor",
            path: "#"
        },
        {
            rawscore: 0.31,
            title: "Another post",
            path: "#"
        }];
    
    // Generate a random number for response, to be able to display various states for backbone app
    res.json({
        message: {
            body: bodyObject.splice(Math.floor(Math.random() * bodyObject.length), Math.round(Math.random() * bodyObject.length) - 1)
        }
    });
});

app.listen(3000, function(){
    console.log('Listening on port %d', 3000);
});
