@api @javascript
Feature: Content lock
  As a content editor
  I should not be able to edit a node
  If that node is being edited by someone else

  @content_lock
  Scenario: Content gets lock and another user cannot edit it until the lock is broken.
    Given I am logged in as a user with the "Author" role
    And I am viewing my "Demo" content titled "Demo site"
    When I click "Edit"
    Then I should see "This content is now locked against simultaneous editing. This content will remain locked if you navigate away from this page without saving or unlocking it."
    # Another user can't edit it.
    Given I am logged in as a user with the "bypass node access, break content lock" permission
    And I visit the "Demo" content titled "Demo site"
    And I click "Edit"
    Then the "Save" button is disabled
    # After breaking the lock the content can be saved.
    When I click "Break lock"
    And I press "Confirm break lock"
    Then I should see "Lock broken. Anyone can now edit this content."
    And I press "Save"
    And all nodes are unlocked
