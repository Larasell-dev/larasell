<?php

namespace Larasell\Larasell\Media;

/**
 * Who may reach an asset's file. Public files have a lasting URL anyone can open, e.g. product
 * images; private ones only a URL that expires, handed out by code that checked who asks.
 */
enum AssetVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
