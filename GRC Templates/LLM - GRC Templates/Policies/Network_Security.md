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

**SCF 2025 (NET-01, NET-02, NET-03, NET-04, NET-05)**

**NIS2 Article 21 (Article 21(2) (e))**

**CIS Controls v8.1 (13.2, 13.3, 13.7, 13.8)**
- Deploy a host-based intrusion detection solution on enterprise assets per 13.2.
- Deploy a network intrusion detection solution on enterprise assets per 13.3.
- Deploy a host-based intrusion prevention solution on enterprise assets per 13.7.
- Deploy a network intrusion prevention solution where appropriate per 13.8.

**NIST 800-53 Rev5 (SC-1, SC-2, SC-3, SC-4, SC-6, SC-9, SC-11, SC-14, SC-15, SC-16, SC-18, SC-19, SC-21, SC-22, SC-24, SC-25, SC-26, SC-27, SC-29, SC-30, SC-31, SC-32, SC-33, SC-34, SC-35, SC-36, SC-37, SC-38, SC-39, SC-40, SC-41, SC-42, SC-43, SC-44, SC-45, SC-46, SC-47, SC-48, SC-49, SC-50, SC-51)**
- Develop and disseminate system and communications protection policy per SC-1.
- Separate user functionality from system management functionality per SC-2.
- Isolate security functions from non-security functions per SC-3.
- Prevent unauthorised and unintended information transfer in shared resources per SC-4.
- Protect resource availability per SC-6.
- Implement transmission confidentiality per SC-9.
- Implement trusted path for communications per SC-11.
- Implement public access protections per SC-14.
- Control collaborative computing devices and applications per SC-15.
- Bind security and privacy attributes to information in transmission per SC-16.
- Control mobile code per SC-18.
- Control VoIP communications per SC-19.
- Implement secure recursive DNS service per SC-21.
- Implement architecture for authoritative DNS service per SC-22.
- Implement fail in known state capability per SC-24.
- Implement thin node controls per SC-25.
- Implement decoys (honeypots) per SC-26.
- Use platform-independent applications where possible per SC-27.
- Implement heterogeneity in system components per SC-29.
- Implement concealment and misdirection per SC-30.
- Conduct covert channel analysis per SC-31.
- Partition systems into components based on classification per SC-32.
- Implement transmission preparation integrity per SC-33.
- Implement non-modifiable executable programs per SC-34.
- Identify external malicious code per SC-35.
- Implement distributed processing and storage per SC-36.
- Employ out-of-band channels for security management per SC-37.
- Implement operations security practices per SC-38.
- Implement process isolation per SC-39.
- Implement wireless link protection per SC-40.
- Control access to ports and I/O devices per SC-41.
- Control sensor capability and data collection per SC-42.
- Implement usage restrictions on system components per SC-43.
- Implement detonation chambers for malicious code per SC-44.
- Implement system time synchronisation per SC-45.
- Implement cross-domain policy enforcement per SC-46.
- Implement alternate communications paths per SC-47.
- Implement sensor relocation capability per SC-48.
- Implement hardware-enforced separation per SC-49.
- Implement software-enforced separation per SC-50.
- Implement hardware-based protection mechanisms per SC-51.

**SCF 2025 (CLD-01, CLD-02, CLD-03, CLD-04, CLD-05, CLD-06, CLD-07, CLD-08, CLD-09, CLD-10, CLD-11, CLD-12, CLD-13, CLD-14, CLD-15, NET-13, NET-16, NET-19, WEB-01, WEB-02, WEB-03, WEB-04, WEB-05, WEB-06, WEB-07, WEB-08, WEB-09, WEB-10, WEB-11, WEB-12, WEB-13, WEB-14)**
- Govern cloud services usage per CLD-01.
- Implement cloud security architecture per CLD-02.
- Implement cloud infrastructure security subnet per CLD-03.
- Implement API security per CLD-04.
- Control virtual machine images per CLD-05.
- Implement multi-tenant environment controls per CLD-06.
- Implement data handling and portability in cloud per CLD-07.
- Use standardised virtualisation formats per CLD-08.
- Address geolocation requirements for data per CLD-09.
- Protect sensitive data in public cloud per CLD-10.
- Implement cloud access security broker per CLD-11.
- Implement side channel attack prevention per CLD-12.
- Govern hosted assets, applications, and services per CLD-13.
- Prohibit unverified hosted assets per CLD-14.
- Implement software-defined storage security per CLD-15.
- Secure electronic messaging per NET-13.
- Secure intranet connections per NET-16.
- Implement content disarm and reconstruction per NET-19.
- Implement web security controls per WEB-01.
- Use demilitarised zones per WEB-02.
- Deploy web application firewall per WEB-03.
- Secure client-facing web services per WEB-04.
- Implement cookie management per WEB-05.
- Implement strong customer authentication per WEB-06.
- Apply web security standards per WEB-07.
- Secure web application frameworks per WEB-08.
- Implement input validation and sanitisation per WEB-09.
- Secure web traffic per WEB-10.
- Implement output encoding per WEB-11.
- Implement web browser security per WEB-12.
- Implement website change detection per WEB-13.
- Review publicly accessible content per WEB-14.

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

**SCF 2025 (NET-06, NET-07, NET-08, NET-09)**

**NIS2 Article 21 (Article 21(2) (e))**

**CIS Controls v8.1 (13.9)**
- Deploy port-level access control using 802.1x or equivalent per 13.9.

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

**SCF 2025 (NET-10, NET-11, NET-12)**

**NIS2 Article 21 (Article 21(2) (j))**

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Firewall Rule Review

**Applies to all frameworks**
1. Export current ruleset and compare against the approved baseline.
2. Remove or justify any rules without a documented business requirement.
3. Document review results and obtain sign-off.
4. Schedule review at least every six months.
5. Confirm all rules restricting CDE traffic are present and correctly configured.

**PCI DSS v4.0.1 (1.2.5, 1.2.7, 1.3.1, 1.3.2)**
6. Confirm review occurs at least every six months per 1.2.7.
7. Confirm all rules restricting CDE traffic are present and correctly configured per 1.3.1 and 1.3.2.
8. Confirm only approved services, protocols, and ports are permitted per 1.2.5.

**SOC 2 (TSP 2017) (CC6.6, CC6.7)**
9. Confirm logical access security measures protect against external threats per CC6.6 and CC6.7.

**ISO 27002:2022 (8.2, 8.21)**
10. Secure, manage, and control network devices to protect information per 8.2.
11. Identify, implement, and monitor security mechanisms for network services per 8.21.

**NIST 800-53 Rev5 (SC-7, CM-6)**
12. Monitor and control communications at external and key internal managed interfaces per SC-7.
13. Establish configuration settings reflecting the most restrictive mode per CM-6.

**CIS Controls v8.1 (12.2, 12.6, 13.4)**
14. Design and maintain a secure network architecture per 12.2.
15. Use secure network management and communication protocols per 12.6.
16. Perform traffic filtering between network segments per 13.4.

**SCF 2025 (NET-03, NET-04)**
17. Monitor and control communications at the external network boundary per NET-03.
18. Implement and govern ACLs for data flow enforcement per NET-04.

**NIS2 Article 21 (Article 21(2) (e))**
19. Confirm firewall rules support security in network and information systems per Article 21(2)(e).

### Network Change

**Applies to all frameworks**
1. Submit change request per the Change Management policy.
2. Assess security impact of the proposed network change.
3. Test in a lab or non-production environment where feasible.
4. Deploy and verify; update network diagrams and documentation.
5. Confirm CDE network diagrams are updated to reflect the change.
6. Verify segmentation controls remain intact post-change.

**PCI DSS v4.0.1 (1.2.2, 1.2.8)**
7. Confirm network diagrams are updated to reflect the change per 1.2.3 and 1.2.4.
8. Confirm all changes follow the defined change control process per 1.2.2.
9. Confirm configuration files are consistent with security standards after change per 1.2.8.

**SOC 2 (TSP 2017) (CC6.6, CC8.1)**
10. Confirm changes are authorised, configured, documented, and tested per CC6.6 and CC8.1.

**ISO 27002:2022 (8.2)**
11. Secure, manage, and control networks and network devices per 8.2.

**NIST 800-53 Rev5 (CM-2, CM-3)**
12. Maintain under configuration control a current baseline configuration per CM-2.
13. Review and approve configuration-controlled changes per CM-3.

**CIS Controls v8.1 (12.1, 12.3, 12.4)**
14. Ensure network infrastructure is kept up to date per 12.1.
15. Securely manage network infrastructure per 12.3.
16. Maintain architecture diagrams per 12.4.

**SCF 2025 (CHG-02, NET-01)**
17. Govern technical configuration change control per CHG-02.
18. Maintain network architecture diagrams reflecting current state per NET-01.

### Wireless Network Authorisation

**Applies to all frameworks**
1. Maintain an inventory of all authorised wireless networks including SSID, location, and business purpose.
2. Scan for unauthorised wireless access points at defined intervals.
3. Investigate and disable any detected unauthorised wireless access points immediately.
4. Confirm all wireless networks are isolated from the CDE unless explicitly approved.
5. Document authorisation status and scan results; retain as evidence.

**PCI DSS v4.0.1 (1.2.3, 1.3.3, 11.2.1, 11.2.2)**
6. Confirm wireless access point inventory is maintained with business justification per 11.2.2.
7. Confirm scanning for unauthorised access points occurs at least every three months per 11.2.1.
8. Confirm all wireless networks are isolated from the CDE per 1.3.3.

**ISO 27002:2022 (8.2)**
9. Secure, manage, and control network devices including wireless per 8.2.

**NIST 800-53 Rev5 (AC-18)**
10. Establish configuration requirements and authorise each type of wireless access per AC-18.

**SCF 2025 (NET-15)**
11. Control authorised wireless usage and monitor for unauthorised wireless access per NET-15.
