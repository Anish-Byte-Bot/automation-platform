use rpc_support::rpc_error::RpcError;
use serde::{Deserialize, Serialize};
#[derive(Serialize, Deserialize, Debug)]
pub struct Metadata {
    pub source: String,
    #[serde(with = "rpc_support::system_time_serializer")]
    pub created_time: std::time::SystemTime,
    pub id: ::uuid::Uuid,
}
#[derive(Serialize, Deserialize, Debug)]
pub struct FileOnMountPath {
    pub path: String,
    pub mount_id: String,
}
#[derive(Serialize, Deserialize, Debug)]
pub struct FileCreated {
    pub path: FileOnMountPath,
}
#[derive(Serialize, Deserialize, Debug)]
pub struct FileMoved {
    pub from: FileOnMountPath,
    pub to: FileOnMountPath,
}
#[derive(Serialize, Deserialize, Debug)]
pub struct FileDeleted {
    pub path: FileOnMountPath,
}
#[derive(Serialize, Deserialize, Debug)]
pub struct FileChanged {
    pub path: FileOnMountPath,
}

#[async_trait]
pub trait Rpc {
    async fn send_file_changed(
        &mut self,
        request: FileChanged,
        metadata: Metadata,
    ) -> Result<(), RpcError>;
    async fn send_file_deleted(
        &mut self,
        request: FileDeleted,
        metadata: Metadata,
    ) -> Result<(), RpcError>;
    async fn send_file_created(
        &mut self,
        request: FileCreated,
        metadata: Metadata,
    ) -> Result<(), RpcError>;
    async fn send_file_moved(
        &mut self,
        request: FileMoved,
        metadata: Metadata,
    ) -> Result<(), RpcError>;
}
