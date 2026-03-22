// ── Base ──────────────────────────────────────────────────────────────────
export interface BaseModel {
  id: number
  created_at?: string
  updated_at?: string
}

// ── Pagination ────────────────────────────────────────────────────────────
export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface PaginationParams {
  page: number
  per_page: number
  sort: string | null
  order: string | null
  search: string | null
  [key: string]: unknown
}

// ── Roles & Permissions ───────────────────────────────────────────────────
export interface Permission extends BaseModel {
  name: string
  module: string
  action: string
  display_name: string
}

export interface Role extends BaseModel {
  name: string
  display_name: string
  description: string | null
  is_system: boolean
  permissions?: Permission[]
  users_count?: number
}

// ── Auth / User ───────────────────────────────────────────────────────────
export type UserRole = string

export interface User extends BaseModel {
  name: string
  user_code: string
  email: string
  role: UserRole
  role_id: number
  permissions?: string[]
  active_modules?: string[]
  is_active: boolean
  avatar: string | null
  structure_id: number | null
}

export interface LoginPayload {
  email: string
  password: string
}

export interface LoginResponse {
  token: string
  user: User
}

// ── Brand ─────────────────────────────────────────────────────────────────
export interface Brand extends BaseModel {
  br_title: string
  br_code?: string
  br_description?: string
  br_status?: boolean
}

// ── Category ──────────────────────────────────────────────────────────────
export interface Category extends BaseModel {
  ctg_title: string
  ctg_code?: string
  ctg_description?: string
  ctg_status?: boolean
}

// ── Product ───────────────────────────────────────────────────────────────
export interface ProductImage extends BaseModel {
  url: string
  title?: string
  isPrimary: boolean
}

export interface Product extends BaseModel {
  p_title: string
  p_code: string
  p_sku: string | null
  p_ean13: string | null
  p_description: string | null
  p_purchasePrice: number
  p_salePrice: number
  p_cost: number
  p_taxRate: number
  p_unit: string
  p_status: boolean
  category_id: number | null
  brand_id: number | null
  category?: Category
  brand?: Brand
  images?: ProductImage[]
  primary_image?: ProductImage | null
}

export interface ProductForm {
  p_title: string
  p_sku: string
  p_ean13: string
  p_purchasePrice: number
  p_salePrice: number
  p_taxRate: number
  p_unit: string
  p_description: string
  p_status: boolean
  category_id: number | null
  brand_id: number | null
}

// ── Third Partner (Customer / Supplier) ───────────────────────────────────
export type PartnerRole = 'customer' | 'supplier' | 'both'
export type TypeCompte = 'normal' | 'en_compte'
export type FrequenceFacturation = 'mensuelle' | 'trimestrielle' | 'semestrielle'

export interface ThirdPartner extends BaseModel {
  tp_title: string
  tp_code: string
  tp_Role: PartnerRole
  tp_status: boolean
  tp_phone: string | null
  tp_email: string | null
  tp_address: string | null
  tp_city: string | null
  tp_Ice_Number: string | null
  tp_Rc_Number: string | null
  tp_patente_Number: string | null
  tp_IdenFiscal: string | null
  encours_actuel: number
  seuil_credit: number
  type_compte: TypeCompte
  frequence_facturation: FrequenceFacturation | null
}

// ── Warehouse ─────────────────────────────────────────────────────────────
export interface Warehouse extends BaseModel {
  wh_title: string
  wh_code?: string
  wh_address?: string
  wh_status?: boolean
}

// ── Document ──────────────────────────────────────────────────────────────
export type DocumentType =
  | 'QuoteSale'
  | 'CustomerOrder'
  | 'DeliveryNote'
  | 'InvoiceSale'
  | 'CreditNoteSale'
  | 'ReturnSale'
  | 'PurchaseOrder'
  | 'ReceiptNotePurchase'
  | 'InvoicePurchase'
  | 'CreditNotePurchase'
  | 'ReturnPurchase'
  | 'StockEntry'
  | 'StockExit'
  | 'StockAdjustmentNote'
  | 'StockTransfer'

export type DocumentStatus =
  | 'draft'
  | 'confirmed'
  | 'converted'
  | 'delivered'
  | 'pending'
  | 'partial'
  | 'paid'
  | 'cancelled'
  | 'applied'

export interface DocumentLigne extends BaseModel {
  document_header_id: number
  product_id: number | null
  sort_order: number
  line_type: string
  designation: string
  reference: string | null
  quantity: number
  unit: string
  unit_price: number
  discount_percent: number
  tax_percent: number
  total_ht?: number
  total_tax?: number
  total_ttc?: number
  product?: Product
}

export interface DocumentFooter extends BaseModel {
  document_header_id: number
  total_ht: number
  total_discount: number
  total_tax: number
  total_ttc: number
  amount_paid: number
  amount_due: number
}

export interface DocumentHeader extends BaseModel {
  reference: string
  document_type: DocumentType
  document_title: string
  parent_id: number | null
  thirdPartner_id: number | null
  user_id: number
  warehouse_id: number | null
  warehouse_dest_id: number | null
  status: DocumentStatus
  issued_at: string | null
  due_at: string | null
  notes: string | null
  third_partner?: ThirdPartner
  thirdPartner?: ThirdPartner
  user?: User
  warehouse?: Warehouse
  warehouseDest?: Warehouse
  lignes?: DocumentLigne[]
  footer?: DocumentFooter
  children?: DocumentHeader[]
  parent?: DocumentHeader
}

// ── Document Incrementor ──────────────────────────────────────────────────
export interface DocumentIncrementor extends BaseModel {
  di_title: string
  di_model: string
  di_domain: string
  template: string
  nextTrick: number
  status: boolean
  operatorSens: 'in' | 'out'
}

// ── Structure Incrementor ─────────────────────────────────────────────────
export interface StructureIncrementor extends BaseModel {
  title: string
  [key: string]: unknown
}

// ── Stock ─────────────────────────────────────────────────────────────────
export type StockDirection = 'in' | 'out'

export interface StockMouvement extends BaseModel {
  product_id: number
  warehouse_id: number
  direction: StockDirection
  reason: string
  quantity: number
  unit_cost: number
  stock_before: number
  stock_after: number
  document_reference: string | null
  document_type: string | null
  user_id: number
  notes: string | null
  product?: Product
  warehouse?: Warehouse
  user?: User
}

export type TransferStatus = 'pending' | 'completed' | 'cancelled'

export interface WarehouseTransfer extends BaseModel {
  from_warehouse_id: number
  to_warehouse_id: number
  product_id: number
  quantity: number
  status: TransferStatus
  notes: string | null
  from_warehouse?: Warehouse
  to_warehouse?: Warehouse
  product?: Product
}

// ── Payment ───────────────────────────────────────────────────────────────
export interface Payment extends BaseModel {
  payment_code: string
  document_header_id: number
  amount: number
  method: string
  paid_at: string
  reference: string | null
  user_id: number
  notes: string | null
}

// ── Notification ──────────────────────────────────────────────────────────
export type NotificationType =
  | 'low_stock'
  | 'order_confirmation'
  | 'invoice_due_reminder'
  | 'payment_received'
  | 'stock_movement'

export interface NotificationData {
  type: NotificationType
  count?: number
  reference?: string
  items?: Array<Record<string, unknown>>
  [key: string]: unknown
}

export interface AppNotification {
  id: string
  type: string
  data: NotificationData
  read_at: string | null
  created_at: string
}

// ── Settings ──────────────────────────────────────────────────────────────
export type Settings = Record<string, Record<string, string>>

// ── Table column ──────────────────────────────────────────────────────────
export interface TableColumn {
  key: string
  label: string
  sortable?: boolean
}

// ── Import result ─────────────────────────────────────────────────────────
export interface ImportResult {
  message: string
  created: number
  updated: number
}

export interface ImportFailure {
  row: number
  attribute: string
  errors: string[]
}

export interface ImportErrors {
  message: string
  failures?: ImportFailure[]
}
