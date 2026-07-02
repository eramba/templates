# Network Security

## Purpose
To protect the organization's network infrastructure from unauthorized access, misuse, and threats by implementing layered network security controls.

## Scope
All network infrastructure including firewalls, routers, switches, wireless networks, cloud virtual networks, and remote access systems.

## Responsibilities

- **IT Operations / Network Engineering**: Design, implement, and maintain network security controls.
- **Information Security**: Define network security requirements, review configurations, and monitor for threats.
- **System Owners**: Ensure systems under their ownership comply with network segmentation and access requirements.

## Policy Statements

### Network Security Controls

**Applies to all frameworks**
- Firewalls and network security controls must be deployed at all boundaries between trusted and untrusted networks.
- All inbound and outbound traffic must be restricted to only that required for business purposes.

**PCI DSS v4.0.1 (1.2.1, 1.2.2, 1.2.3, 1.2.4, 1.2.5, 1.2.6, 1.2.7, 1.2.8)**
- Configuration standards for network security controls must be defined, implemented, and maintained.
- Network security control configurations must be reviewed at least every six months.
- All changes to network security configurations must follow the change management process.

**ISO 27002:2022 (8.2, 8.21)**
- Network services must be secured, monitored, and subject to access controls regardless of whether they are provided internally or outsourced.

**SOC 2 (TSP 2017) (CC6.6, CC6.7)**
- Network controls must support detection of and protection against unauthorized access attempts.

**NIST 800-53 Rev5 (SC-5, SC-7, SC-10)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7, 12.8)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7, 12.8 for specific safeguard requirements.

**SCF 2025 (NET-01, NET-02, NET-03, NET-04, NET-05)**
- Implement SCF 2025 controls applicable to this area; refer to controls NET-01, NET-02, NET-03, NET-04, NET-05 for specific requirements.

**NIS2 Article 21 (Article 21(2) (e))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (e)); measures must be proportionate to the organisation's risk exposure and size.

### Network Segmentation

**Applies to all frameworks**
- Networks must be segmented based on data sensitivity and functional requirements.
- Direct connections between untrusted networks and systems storing sensitive data are prohibited.

**PCI DSS v4.0.1 (1.3.1, 1.3.2, 1.3.3, 1.4.1, 1.4.2, 1.4.3, 1.4.4, 1.4.5)**
- The cardholder data environment must be isolated from other network segments.
- Wireless networks must be isolated from wired internal networks unless explicitly authorized and controlled.
- End-user devices must not be able to directly access sensitive data stores without passing through security controls.

**ISO 27002:2022 (8.22)**
- Segmentation boundaries must be defined and enforced through technical controls.

**SOC 2 (TSP 2017) (CC6.6, CC6.7, CC6.8)**
- Logical access controls must restrict connectivity between network zones based on business need.

**NIST 800-53 Rev5 (SC-7, CA-9)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (12.2, 13.4, 13.5)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 12.2, 13.4, 13.5 for specific safeguard requirements.

**SCF 2025 (NET-06, NET-07, NET-08, NET-09)**
- Implement SCF 2025 controls applicable to this area; refer to controls NET-06, NET-07, NET-08, NET-09 for specific requirements.

**NIS2 Article 21 (Article 21(2) (e))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (e)); measures must be proportionate to the organisation's risk exposure and size.

### Remote Access

**Applies to all frameworks**
- All remote access to internal systems must be via approved, encrypted channels.
- Multi-factor authentication must be enforced for all remote access.
- Remote access sessions must be logged and subject to inactivity timeouts.

**PCI DSS v4.0.1 (1.5.1)**
- Split tunneling must be disabled or explicitly approved and documented.

**NIST 800-53 Rev5 (SC-8, SC-20, SC-23)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (9.5, 9.6, 9.7)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 9.5, 9.6, 9.7 for specific safeguard requirements.

**SCF 2025 (NET-10, NET-11, NET-12)**
- Implement SCF 2025 controls applicable to this area; refer to controls NET-10, NET-11, NET-12 for specific requirements.

**NIS2 Article 21 (Article 21(2) (j))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (j)); measures must be proportionate to the organisation's risk exposure and size.

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Firewall Rule Review

**Applies to all frameworks**
1. Export current ruleset and compare against the approved baseline.
2. Remove or justify any rules without a documented business requirement.
3. Document review results and obtain sign-off.

**PCI DSS v4.0.1 (1.2.5, 1.2.6, 1.2.7)**
4. Schedule review at least every six months.
5. Confirm all rules restricting CDE traffic are present and correctly configured.

**NIST 800-53 Rev5 (SC-7)**
6. Document compliance with applicable NIST 800-53 Rev5 controls (SC-7) in the system security plan.

**CIS Controls v8.1 (12.1, 12.3)**
7. Document compliance with applicable CIS Controls v8.1 controls (12.1, 12.3) per organisational policy.

**SCF 2025 (NET-02, NET-03, NET-04)**
8. Document compliance with applicable SCF 2025 controls (NET-02, NET-03, NET-04) per organisational policy.

**NIS2 Article 21 (Article 21(2) (e))**
9. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (e)) as applicable to the organisation's classification under NIS2.

### Network Change

**Applies to all frameworks**
1. Submit change request per the Change Management policy.
2. Assess security impact of the proposed network change.
3. Test in a lab or non-production environment where feasible.
4. Deploy and verify; update network diagrams and documentation.

**PCI DSS v4.0.1 (1.2.1, 1.2.8)**
5. Confirm CDE network diagrams are updated to reflect the change.
6. Verify segmentation controls remain intact post-change.

**NIST 800-53 Rev5 (CM-3, SC-7)**
7. Document compliance with applicable NIST 800-53 Rev5 controls (CM-3, SC-7) in the system security plan.

**CIS Controls v8.1 (12.1, 12.4)**
8. Document compliance with applicable CIS Controls v8.1 controls (12.1, 12.4) per organisational policy.

**SCF 2025 (NET-01, NET-05)**
9. Document compliance with applicable SCF 2025 controls (NET-01, NET-05) per organisational policy.

**NIS2 Article 21 (Article 21(2) (e))**
10. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (e)) as applicable to the organisation's classification under NIS2.

### Wireless Network Authorisation

**PCI DSS v4.0.1 (1.2.3, 1.3.3)**
1. Maintain an inventory of all authorised wireless networks including SSID, location, and business purpose.
2. Scan for unauthorised wireless access points at defined intervals.
3. Investigate and disable any detected unauthorised wireless access points immediately.
4. Confirm all wireless networks are isolated from the CDE unless explicitly approved.
5. Document authorisation status and scan results; retain as evidence.

**NIST 800-53 Rev5 (SC-7)**
6. Document compliance with applicable NIST 800-53 Rev5 controls (SC-7) in the system security plan.

**CIS Controls v8.1 (12.6, 12.7, 12.8)**
7. Document compliance with applicable CIS Controls v8.1 controls (12.6, 12.7, 12.8) per organisational policy.

**SCF 2025 (NET-13, NET-14, NET-15)**
8. Document compliance with applicable SCF 2025 controls (NET-13, NET-14, NET-15) per organisational policy.

**NIS2 Article 21 (Article 21(2) (e))**
9. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (e)) as applicable to the organisation's classification under NIS2.
