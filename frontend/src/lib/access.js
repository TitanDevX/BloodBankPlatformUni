export const roles = {
  administrator: { label: 'Network administrator', scope: 'Network workspace', nav: ['Overview', 'Organizations', 'Inventory', 'Allocations & issues', 'Traceability', 'Reports', 'Demand forecast', 'Staff & access', 'Audit trail'] },
  bank_admin: { label: 'Bank administrator', scope: 'Blood bank workspace', nav: ['Overview', 'Organizations', 'Inventory', 'Traceability', 'Reports', 'Demand forecast', 'Staff & access'] },
  bank_donor_collection: { label: 'Donor & collection staff', scope: 'Blood bank workspace', nav: ['Overview', 'Organizations', 'Donors', 'Donations'] },
  bank_inventory: { label: 'Inventory staff', scope: 'Blood bank workspace', nav: ['Overview', 'Organizations', 'Inventory', 'Components', 'Expiry & disposal'] },
  bank_fulfillment: { label: 'Fulfillment staff', scope: 'Blood bank workspace', nav: ['Overview', 'Organizations', 'Inventory', 'Requests', 'Allocations & issues', 'Traceability'] },
  laboratory_testing: { label: 'Laboratory testing staff', scope: 'Laboratory workspace', nav: ['Overview', 'Organizations', 'Screening results'] },
  laboratory_release: { label: 'Laboratory release staff', scope: 'Laboratory workspace', nav: ['Overview', 'Organizations', 'Release decisions', 'Donor notifications'] },
  hospital_requester: { label: 'Hospital requester', scope: 'Hospital workspace', nav: ['Overview', 'Organizations', 'Requests', 'Issue records'] },
};
export function roleCode(user) { return typeof user?.role === 'string' ? user.role.replace(/^roles\//, '') : user?.role?.code; }
export function getAccess(user) { return roles[roleCode(user)] ?? null; }
