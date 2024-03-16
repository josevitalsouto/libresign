Feature: admin/certificate_openssl
  Scenario: Generate root cert with success using only required values
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"Common Name"} |
    And the response should have a status code 200
    Then sending "get" to ocs "/apps/libresign/api/v1/admin/certificate"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key        | value                                   |
      | rootCert   | {"commonName":"Common Name","names":[]} |
      | generated  | true                                    |

  Scenario: Generate root cert with success using optional names values
    Given as user "admin"
    When sending "post" to ocs "/apps/libresign/api/v1/admin/certificate/openssl"
      | rootCert | {"commonName":"Common Name","names":[{"id": "C","value":"BR"}]} |
    And the response should have a status code 200
    Then sending "get" to ocs "/apps/libresign/api/v1/admin/certificate"
    And the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key        | value                                                          |
      | rootCert   | {"commonName":"Common Name","names":[{"id":"C","value":"BR"}]} |
      | generated  | true                                                           |
