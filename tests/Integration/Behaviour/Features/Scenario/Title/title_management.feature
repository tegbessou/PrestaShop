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
    When I add new title "title1" with following properties:
      | localised_names[en-US] | Doctor       |
      | localised_names[fr-FR] | Dr.          |
      | gender                 | 2            |
      | picture_width          | 16           |
      | picture_height         | 16           |
      | image                  | app_icon.png |
    Then title "title1" should have following properties:
      | localised_names[en-US] | Doctor |
      | localised_names[fr-FR] | Dr.    |
      | gender                 | 2      |

  Scenario: Edit existing contact
    When I add new title "title2" with following properties:
      | localised_names[en-US] | Ms.   |
      | localised_names[fr-FR] | Mlle. |
      | gender                 | 1     |
    And I update title "title2" with following properties:
      | localised_names[en-US] | Widow        |
      | localised_names[fr-FR] | Vve.         |
      | gender                 | 2            |
      | picture_width          | 16           |
      | picture_height         | 16           |
      | image                  | app_icon.png |
    Then title "title2" should have following properties:
      | localised_names[en-US] | Widow |
      | localised_names[fr-FR] | Vve.  |
      | gender                 | 2     |
