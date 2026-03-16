---
- name: Replace old credential with new credential in job templates
  hosts: localhost
  gather_facts: false

  vars_prompt:
    - name: controller_username
      prompt: "Enter controller username"
      private: no

    - name: controller_password
      prompt: "Enter controller password"
      private: yes

  vars:
    # Controller URL
    controller_url: "https://your-controller-url"

    # Credentials to replace
    old_cred_name: "old_credential_name"
    new_cred_name: "new_credential_name"

    # Test mode:
    # Put template name to test one template
    # Leave empty ("") to process all templates
    target_template_name: ""

    # Preview mode:
    # false = preview only
    # true = apply changes
    perform_changes: false

    validate_certs: false
    page_size: 200

  tasks:
    - name: Get old credential details
      uri:
        url: "{{ controller_url }}/api/v2/credentials/?name={{ old_cred_name | urlencode }}"
        method: GET
        user: "{{ controller_username }}"
        password: "{{ controller_password }}"
        force_basic_auth: true
        validate_certs: "{{ validate_certs }}"
        return_content: true
      register: old_cred_resp

    - name: Get new credential details
      uri:
        url: "{{ controller_url }}/api/v2/credentials/?name={{ new_cred_name | urlencode }}"
        method: GET
        user: "{{ controller_username }}"
        password: "{{ controller_password }}"
        force_basic_auth: true
        validate_certs: "{{ validate_certs }}"
        return_content: true
      register: new_cred_resp

    - name: Fail if old credential not found
      fail:
        msg: "Old credential '{{ old_cred_name }}' was not found."
      when: old_cred_resp.json.count | int == 0

    - name: Fail if new credential not found
      fail:
        msg: "New credential '{{ new_cred_name }}' was not found."
      when: new_cred_resp.json.count | int == 0

    - name: Set credential IDs
      set_fact:
        old_cred_id: "{{ old_cred_resp.json.results[0].id }}"
        new_cred_id: "{{ new_cred_resp.json.results[0].id }}"

    - name: Get selected template
      uri:
        url: "{{ controller_url }}/api/v2/job_templates/?name={{ target_template_name | urlencode }}"
        method: GET
        user: "{{ controller_username }}"
        password: "{{ controller_password }}"
        force_basic_auth: true
        validate_certs: "{{ validate_certs }}"
        return_content: true
      register: selected_template_resp
      when: target_template_name | length > 0

    - name: Fail if template not found
      fail:
        msg: "Template '{{ target_template_name }}' was not found."
      when:
        - target_template_name | length > 0
        - selected_template_resp.json.count | int == 0

    - name: Set candidate templates for test mode
      set_fact:
        candidate_templates: "{{ selected_template_resp.json.results }}"
      when: target_template_name | length > 0

    - name: Get all job templates
      uri:
        url: "{{ controller_url }}/api/v2/job_templates/?page_size={{ page_size }}"
        method: GET
        user: "{{ controller_username }}"
        password: "{{ controller_password }}"
        force_basic_auth: true
        validate_certs: "{{ validate_certs }}"
        return_content: true
      register: templates_resp
      when: target_template_name | length == 0

    - name: Set candidate templates for all mode
      set_fact:
        candidate_templates: "{{ templates_resp.json.results }}"
      when: target_template_name | length == 0

    - name: Initialize matching template list
      set_fact:
        matching_templates: []

    - name: Get credentials for each template
      uri:
        url: "{{ controller_url }}/api/v2/job_templates/{{ item.id }}/credentials/"
        method: GET
        user: "{{ controller_username }}"
        password: "{{ controller_password }}"
        force_basic_auth: true
        validate_certs: "{{ validate_certs }}"
        return_content: true
      loop: "{{ candidate_templates }}"
      register: template_creds_resp
      loop_control:
        label: "{{ item.name }}"

    - name: Build list of templates using old credential
      set_fact:
        matching_templates: "{{ matching_templates + [ {'id': item.item.id, 'name': item.item.name} ] }}"
      loop: "{{ template_creds_resp.results }}"
      when: old_cred_id in (item.json.results | map(attribute='id') | list)

    - name: Show matching templates
      debug:
        msg:
          - "Old credential: {{ old_cred_name }}"
          - "New credential: {{ new_cred_name }}"
          - "Preview only: {{ not perform_changes }}"
          - "Templates to update: {{ matching_templates | map(attribute='name') | list }}"

    - name: Stop if preview mode
      meta: end_play
      when: not perform_changes

    - name: Attach new credential
      uri:
        url: "{{ controller_url }}/api/v2/job_templates/{{ item.id }}/credentials/"
        method: POST
        user: "{{ controller_username }}"
        password: "{{ controller_password }}"
        force_basic_auth: true
        validate_certs: "{{ validate_certs }}"
        body_format: json
        body:
          id: "{{ new_cred_id }}"
        status_code: [200, 201, 204]
      loop: "{{ matching_templates }}"
      loop_control:
        label: "{{ item.name }}"

    - name: Remove old credential
      uri:
        url: "{{ controller_url }}/api/v2/job_templates/{{ item.id }}/credentials/"
        method: POST
        user: "{{ controller_username }}"
        password: "{{ controller_password }}"
        force_basic_auth: true
        validate_certs: "{{ validate_certs }}"
        body_format: json
        body:
          id: "{{ old_cred_id }}"
          disassociate: true
        status_code: [200, 201, 204]
      loop: "{{ matching_templates }}"
      loop_control:
        label: "{{ item.name }}"
