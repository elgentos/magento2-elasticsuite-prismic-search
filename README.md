# elgentos/magento2-elasticsuite-prismic-search

This extension adds search (incl. autocomplete) support for Prismic custom types in ElasticSuite.

## Configuration

Under Stores > Configuration > ElasticSuite > Prismic Settings you can set certain configuration.

You can also configure certain blocks to be removed from the rendered result. Use the `content-type::block-name` notation to target specific blocks inside specific content types. For example, if you have a slice called `banner` in the content type `landing_page`, you can enter `landing_page::banner` to exclude the text in that slice.

![image](https://user-images.githubusercontent.com/431360/178054241-cb28ff73-275f-4d38-9e16-60c224721301.png)

## Installation

```
composer require elgentos/magento2-elasticsuite-prismic-search
bin/magento set:up 
```

## Usage

```
bin/magento ind:reindex elasticsuite_prismic_fulltext
```
