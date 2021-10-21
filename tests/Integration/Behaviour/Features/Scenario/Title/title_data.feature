# ./vendor/bin/behat -c tests/Integration/Behaviour/behat.yml -s title --tags title-data
@reset-database-before-feature
Feature: Title Data
  PrestaShop provides handlers for title data
  As a BO user
  I must be able to get data for a title

  Background:
    Given shop "shop1" with name "test_shop" exists
    And language "language1" with locale "en-US" exists
    And language "language2" with locale "fr-FR" exists

  @title-data
  Scenario: Get data from a title
    When I request reference data for 1
    Then I should get no error
    And I should get title data:
      | title_id               | 1        |
      | localised_names[en-US] | Mr.        |
      | gender                 | 0          |
