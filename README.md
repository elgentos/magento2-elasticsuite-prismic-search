# elgentos/magento2-elasticsuite-prismic-search

This extension adds search (incl. autocomplete) support for Prismic custom types in ElasticSuite.

## Configuration

Under Stores > Configuration > ElasticSuite > Prismic Settings you can define which content types will be indexed. 

You can also configure certain blocks to be removed from the rendered result. By default we remove the header, footer, etc.

![image](https://user-images.githubusercontent.com/431360/177497365-103b6ce5-e74a-4199-a641-3c7649d148b2.png)

## Installation

```
composer require elgentos/magento2-elasticsuite-prismic-search
bin/magento set:up 
```

## Usage

```
bin/magento ind:reindex elasticsuite_prismic_fulltext
```
