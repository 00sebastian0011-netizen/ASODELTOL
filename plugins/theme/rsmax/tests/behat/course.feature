@theme @theme_rsmax
Feature: Follow a course with the RSMAX theme
  In order to know where I am in a course and what to do next
  As a student
  I need the course pages to show my progress

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Marta     | Soler    | teacher1@example.com |
      | student1 | Sam       | Reed     | student1@example.com |
      | student2 | Noa       | Vidal    | student2@example.com |
    And the following "courses" exist:
      | fullname           | shortname | enablecompletion | summary                      |
      | Project management | PM        | 1                | From the idea to the result. |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | PM     | editingteacher |
      | student1 | PM     | student        |
    And the following "activities" exist:
      | activity | course | name              | idnumber | section | completion |
      | page     | PM     | What is a project | page1    | 1       | 1          |
      | page     | PM     | Planning the work | page2    | 1       | 1          |

  Scenario: The course page shows the progress of the student and where to continue
    When I am on the "Project management" course page logged in as student1
    Then I should see "Project management" in the ".rsmax-coursehero" "css_element"
    And I should see "Marta Soler" in the ".rsmax-coursehero" "css_element"
    And I should see "Your progress" in the ".rsmax-coursehero" "css_element"
    And I should see "0 of 2 activities completed" in the ".rsmax-coursehero" "css_element"
    And I should see "What is a project" in the ".rsmax-coursehero" "css_element"

  Scenario: An activity page shows its place in the section and the way to the next one
    When I am on the "What is a project" "page activity" page logged in as student1
    Then I should see "What is a project" in the ".rsmax-stage" "css_element"
    And I should see "Project management" in the ".rsmax-stage" "css_element"
    And I should see "Planning the work" in the ".rsmax-dock" "css_element"

  @javascript
  Scenario: The course pages work in a browser, from the course to an activity and on to the next
    Given I am on the "Project management" course page logged in as student1
    And I should see "Your progress" in the ".rsmax-coursehero" "css_element"
    When I click on "What is a project" "link" in the ".rsmax-coursehero" "css_element"
    Then I should see "What is a project" in the ".rsmax-stage" "css_element"
    And I click on "Planning the work" "link" in the ".rsmax-dock" "css_element"
    And I should see "Planning the work" in the ".rsmax-stage" "css_element"

  Scenario: A teacher sees the course without a progress of their own
    When I am on the "Project management" course page logged in as teacher1
    Then I should see "Project management" in the ".rsmax-coursehero" "css_element"
    And I should not see "Your progress"

  Scenario: Somebody who is not in the course is shown what the course is about
    Given I log in as "admin"
    And I add "Self enrolment" enrolment method in "Project management" with:
      | Custom instance name | Open door |
    And I log out
    When I am on the "Project management" course page logged in as student2
    Then I should see "Project management" in the ".rsmax-enrolhero" "css_element"
    And I should see "From the idea to the result."
    And I should see "Marta Soler"
    And "Enrol me" "button" should exist

  Scenario: A teacher can open the page of the banners of the course
    Given I am on the "Project management" course page logged in as teacher1
    When I navigate to "Banners" in current page administration
    Then I should see "Banners" in the "#region-main" "css_element"
