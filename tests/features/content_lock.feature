@api @javascript
Feature: Content lock
  As a content editor
  When I edit a node I want the node to be locked so that other users can't edit it

  @content_lock
  Scenario: Content gets lock and lock can be broken
    Given I am logged in as a user with the "Author" role
    And I am viewing my "oe_workflow_demo" with the title "Demo site"
    When I click "Edit"
    Then I should see "This content is now locked against simultaneous editing. This content will remain locked if you navigate away from this page without saving or unlocking it."
    # And I can unlock the node.
    And I click "Unlock"
    And I press "Confirm break lock"
    Then I should see "Lock broken. Anyone can now edit this content."
