# DataTable

This library handles client and server side record fetching, including search, sort and pagination.

A script file is provided for convenience which will automatically set up the tables client side also. Feel free to write your own, or extend `PerryRylance.DataTable` by overriding `createInstance`.

## Requirements

- This library is designed to be used in an environment with Illuminates DB interface
- If you would like to use the JavaScript module provided, jQuery must be loaded before the script file

## Installation

I recommend installing this via Composer:

`composer require perry-rylance/datatable`

## Usage

- Make a subclass of `PerryRylance\DataTable`.
- You must implement the abstract method `getTableName`.
- You must also implement the abstract method `getRoute`.