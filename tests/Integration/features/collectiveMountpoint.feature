Feature: collectiveMountpoint

  Scenario: Set edit and share permissions for collective and check mountpoint permissions
    When user "jane" creates collective "mycollective"
    And user "jane" sets "edit" level in collective "mycollective" to "Admin"
    And user "jane" sets "share" level in collective "mycollective" to "Moderator"
    And user "john" joins circle "mycollective" with owner "jane" with level "Admin"
    And user "alice" joins circle "mycollective" with owner "jane" with level "Moderator"
    And user "bob" joins circle "mycollective" with owner "jane"
    Then user "jane" has webdav access to "mycollective" with permissions "RMGDNVCK"
    And user "john" has webdav access to "mycollective" with permissions "RMGDNVCK"
    And user "alice" has webdav access to "mycollective" with permissions "RMG"
    And user "bob" has webdav access to "mycollective" with permissions "MG"

  Scenario: Trash and delete collective and circle
    Then user "jane" trashes collective "mycollective"
    And user "jane" deletes collective+circle "mycollective"
