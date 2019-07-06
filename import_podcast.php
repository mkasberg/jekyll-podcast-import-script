#!/usr/bin/env php
<?php

// A PHP Script to migrate an iTunes podcast from WordPress (or any other platform) to Jekyll.
// Given a feed.xml file, grab all the episodes out of it and crate posts for them.

$usage = <<<EOD
Usage: import_podcast.php feed.xml

Given a podcast feed.xml file, generate Jekyll posts for the podcast.


EOD;

$shortOpts = 'h';
$longOpts = array('help');
$options = getopt($shortOpts, $longOpts);

if (isset($options['h']) || isset($options['help']))
{
    print $usage;
    exit;
}

if (!isset($argv[1]))
{
    print $usage;
    exit;
}

$filename = $argv[1];

if (!file_exists($filename))
{
    print "File $filename does not exist!\n";
}

$feed = simplexml_load_file($filename);

@mkdir('_posts');

foreach($feed->channel->item as $item)
{
    writePost($item);
}

exit;

// =============================================================================
// Helper functions

/**
 * Build a Jekyll post.md from an RSS feed item.
 * @param SimpleXMLElement $item
 */
function writePost(SimpleXMLElement $item)
{
    $iTunes = $item->children('http://www.itunes.com/dtds/podcast-1.0.dtd');

    $date = new DateTime($item->pubDate);
    $filename = $date->format('Y-m-d') . '-' . slugify($item->title) . '.md';

    $audioFile = $item->enclosure['url'];

    $tags = explode(',', $iTunes->keywords);
    $tags = array_map('trim', $tags);
    $tags = array_map('slugify', $tags);

    // yaml_emit() isn't always available or easy to install, so we'll do it
    // manually.
    $post = "---\n";
    $post .= "layout: post\n";
    $post .= "date: " . $date->format('Y-m-d H:i:s') . "\n";
    $post .= "audio: '" . escapeYaml($audioFile) ."'\n";
    $post .= "title: '" . escapeYaml($item->title) . "'\n";
    $post .= "subtitle: '" . escapeYaml($iTunes->subtitle) . "'\n";
    $post .= "author: '" . escapeYaml($iTunes->author) . "'\n";
    $post .= "duration: $iTunes->duration\n";
    $post .= "guid: '" . escapeYaml($item->guid) . "'\n";
    $post .= "explicit: $iTunes->explicit\n";
    $post .= "tags: [" . implode(', ', $tags) . "]\n";
    $post .= "categories: podcast\n";
    $post .= "summary: '" . escapeYaml($iTunes->summary) . "'\n";
    $post .= "---\n";

    $post .= $item->description . "\n";

    file_put_contents('_posts/' . $filename, $post);
}

/**
 * Replace all non-alphanumeric characters with '-'.
 * @param string $text
 */
function slugify($text)
{
    $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $text);
    $slug = trim($slug, '-');
    $slug = strtolower($slug);
    return $slug;
}

/**
 * Prepare a string to go inside a single-quoted YAML string.
 * @param string $string
 */
function escapeYaml($string)
{
    // Just replace a single quote with two single quotes.
    return str_replace("'", "''", $string);
}

