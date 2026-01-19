<template>
  <div id="app">
    <div class="sidebar">
      <h1>Brickventory</h1>
      <nav>
        <NuxtLink to="/" exact-active-class="active">Dashboard</NuxtLink>
        <NuxtLink to="/purchase-orders" exact-active-class="active">Purchase Orders</NuxtLink>
        <NuxtLink to="/item-receipts" exact-active-class="active">Item Receipts</NuxtLink>
        <NuxtLink to="/inventory-adjustments" exact-active-class="active">Inventory Adjustments</NuxtLink>
        <NuxtLink to="/sales-orders" exact-active-class="active">Sales Orders</NuxtLink>
        <NuxtLink to="/items" exact-active-class="active">Items</NuxtLink>
      </nav>
    </div>
    <div class="main-content">
      <NuxtPage />
    </div>
  </div>
</template>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  line-height: 1.6;
}

#app {
  display: flex;
  height: 100vh;
}

.sidebar {
  width: 250px;
  background: #2c3e50;
  color: white;
  padding: 20px;
}

.sidebar h1 {
  font-size: 1.5rem;
  margin-bottom: 30px;
}

.sidebar nav a {
  display: block;
  color: white;
  text-decoration: none;
  padding: 10px;
  margin-bottom: 5px;
  border-radius: 4px;
  transition: background 0.2s;
}

.sidebar nav a:hover,
.sidebar nav a.active {
  background: #34495e;
}

.main-content {
  flex: 1;
  padding: 30px;
  overflow-y: auto;
  background: #ecf0f1;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.header h2 {
  color: #2c3e50;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: opacity 0.2s;
}

.btn:hover {
  opacity: 0.8;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: #3498db;
  color: white;
}

.btn-secondary {
  background: #95a5a6;
  color: white;
}

.btn-success {
  background: #27ae60;
  color: white;
}

.btn-danger {
  background: #e74c3c;
  color: white;
}

.btn-small {
  padding: 5px 10px;
  font-size: 12px;
  margin-right: 5px;
}

.card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-bottom: 20px;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  text-align: left;
  padding: 12px;
  border-bottom: 1px solid #ecf0f1;
}

th {
  background: #f8f9fa;
  font-weight: 600;
  color: #2c3e50;
}

tr:hover {
  background: #f8f9fa;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  color: #2c3e50;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.form-group textarea {
  resize: vertical;
  min-height: 80px;
}

.line-items {
  margin-top: 20px;
}

.line-item {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr auto;
  gap: 10px;
  margin-bottom: 10px;
  align-items: end;
}

.actions {
  display: flex;
  gap: 5px;
}

.error {
  color: #e74c3c;
  font-size: 14px;
  margin-top: 5px;
}

.success {
  color: #27ae60;
  font-size: 14px;
  margin-top: 5px;
}
</style>
