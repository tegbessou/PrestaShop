# ./vendor/bin/behat -c tests/Integration/Behaviour/behat.yml -s title --tags title-management
@reset-database-before-feature
@clear-cache-after-feature
@reset-downloads-after-feature
@title
@title-management
Feature: Manage title from Back Office (BO)
  As an employee I want to be able to add, update and delete titles

  Scenario: I add new title
    Given language "language1" with locale "en-US" exists
    And language with iso code "en" is the default one
    And language "language2" with locale "fr-FR" exists
    When I add new title with following properties:
      | localised_names[en-US] | Doctor       |
      | localised_names[fr-FR] | Dr.          |
      | gender                 | 2            |
      | picture_width          | 16           |
      | picture_height         | 16           |
      | image                  | app_icon.png |
    Then the title with ID "3" should exist

    When I request reference data for "3"
    Then I should get title data:
      | localised_names[en-US] | Doctor |
      | localised_names[fr-FR] | Dr.    |
      | gender                 | 2      |

  Scenario: Edit existing title
    Given the title with ID "1" should exist
    When I update title "1" with following properties:
      | localised_names[en-US] | Widow        |
      | localised_names[fr-FR] | Vve.         |
      | gender                 | 2            |
      | picture_width          | 16           |
      | picture_height         | 16           |
      | image                  | app_icon.png |

    When I request reference data for "1"
    Then I should get title data:
      | localised_names[en-US] | Widow |
      | localised_names[fr-FR] | Vve.  |
      | gender                 | 2     |

  Scenario: Delete existing title
    Given language "language1" with locale "en-US" exists
    And language with iso code "en" is the default one
    And language "language2" with locale "fr-FR" exists
    When I add new title with following properties:
      | localised_names[en-US] | Ms.   |
      | localised_names[fr-FR] | Mlle. |
      | gender                 | 1     |
    Then the title with ID "2" should exist

    When I delete title with ID "2"
    Then the title with ID "2" shouldn't exist

  Scenario: Bulk delete existing title
    Given language "language1" with locale "en-US" exists
    And language with iso code "en" is the default one
    And language "language2" with locale "fr-FR" exists
    When I add new title with following properties:
      | localised_names[en-US] | Ms.   |
      | localised_names[fr-FR] | Mlle. |
      | gender                 | 1     |
    And I add new title with following properties:
      | localised_names[en-US] | Ms.   |
      | localised_names[fr-FR] | Mlle. |
      | gender                 | 1     |
    Then the title with ID "3" should exist
    And the title with ID "4" should exist

    When I bulk delete titles with ID "3,4"
    Then the title with ID "3" shouldn't exist
    And the title with ID "4" shouldn't exist
